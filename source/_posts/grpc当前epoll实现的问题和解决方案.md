---
title: gRPC当前epoll实现的问题和解决方案
tags: []
id: '1589'
categories:
  - - my_tutorials
    - gRPC
  - - 我的教程
date: 2019-07-24 15:56:41
---

gRPC当前的epoll实现并不十分高效，有很大的改进空间。这篇文章来分析一下。

`epoll`是gRPC实现pollset的基础。因此，你有必要先了解一下epoll即其发展史（至少了解EPOLLEXCLUSIVE是干什么的吧？）

如果文章中的内容不能理解，建议先看下我之前讲gRPC的相关文章。

### 介绍  

### 当前gRPC中`epoll`的实现.  
整体架构图:

![](http://www.anger6.com/wp-content/uploads/2019/07/old_epoll_impl.png)

一个gRPC客户端或者服务端都可以有多个completion queue(后面简称为cq),每个cq都会创建一个pollset.

gRPC核心库自己不会创建任何线程，线程的创建取决于使用gRPC核心库的应用程序。

线程通过调用API`grpc_completion_queue_next()`或者`grpc_completion_queue_pluck()`开始事件poll.

在同一个cq上可以有多个线程同时调用`grpc_completion_queue_next()`.

一个文件描述符fd可以加入到多个cq中.

当epoll\_set上有事件产生时，有多个线程可能被唤醒，不能保证哪个线程最终执行事件相关的callbacks.

执行工作的线程最终会向合适的cq中放入一个completion event（完成事件，后面简称`ce`）`grpc_cq_completion`，然后将对这个ce感兴趣的线程"kicks"(唤醒，一般使用eventfd实现).这里需要注意的是这个线程可能是自己。

#### 举个例子：

  
假设上图中的fd\_1变为可读状态，Thread1~ThreadK, ThreadP都有可能被唤醒。

我们假设ThreadP因为关心fd1上的事件而调用了`grpc_completion_queue_pluck()`,但是被唤醒的是Thread1。

在这种情况下，_Thread1_执行完相应的callbacks后并最终通过event\_fd\_P来唤醒"kicks " _ThreadP_.

_ThreadP_被唤醒，然后知道有一个ce并将它返回给`grpc_completion_queue_pluck()`的调用者。

### 当前架构的问题  
惊群效应

#### 今天儿有点儿晚了，明天继续吧。^\_^

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/97182780  
版权声明：本文为博主原创文章，转载请附上博文链接！
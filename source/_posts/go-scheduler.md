---
title: Go为什么要做用户态调度
tags: []
id: '1945'
categories:
  - - program_language
    - Golang
  - - 编程语言
date: 2019-08-20 13:14:50
---

Go1.1中一个大的特性是Dmitry Vyukov实现的新的调度器. 新的调度器为并行的Go程序带来了巨大的性能提升.因此，我必须写这篇文章来介绍一下.

这里是原始的[设计文档](https://docs.google.com/document/d/1TTj4T2JO42uD5ID9e89oa0sLKhJYD0Y_kqxDv3I3XMw)的链接.设计文档里有所有你想知道的细节，本篇文章通过更多的图片来使描述更加清晰.

# 为什么Go的运行时需要一个调度器?

在我们详细了解新的调度器之前，我们需要先来看看为什么的问题?

操作系统已经提供了线程调度的功能，为什么还要在用户态实现一个调度器?

POSIX线程API在很大程度上是对现有Unix进程模型的扩展,线程获得了许多和进程同样的控件.线程有它们自己的信号掩码,能够设置CPU亲和力,能够通过cgroups设置可以使用的资源.所有这些控制都增加了额外的开销，当你的程序使用100,000个线程时这些开销快速地增加,而这些对使用goroutines的go程序没有必要.

另外一个问题是:OS不能基于go模型对调度做出正确的决策.比如,Go的gc需要所有线程停止运行，而且内存必须处于一致的状态.这在Go中是通过等待运行的线程运行到某一点，我们知道这时内存处于一致的状态.

当你有很多可以在任意时间点调度的线程,那么你就需要等它们运行到一个一致状态的点.但是Go调度器可以知道内存一致的状态并只在此时进行调度.When you have many threads scheduled out at random points, chances are that you're going to have to wait for a lot of them to reach a consistent state. The Go scheduler can make the decision of only scheduling at points where it knows that memory is consistent. This means that when we stop for garbage collection, we only have to wait for the threads that are being actively run on a CPU core.

# Our Cast of Characters

There are 3 usual models for threading. One is N:1 where several userspace threads are run on one OS thread. This has the advantage of being very quick to context switch but cannot take advantage of multi-core systems. Another is 1:1 where one thread of execution matches one OS thread. It takes advantage of all of the cores on the machine, but context switching is slow because it has to trap through the OS.

Go tries to get the best of both worlds by using a M:N scheduler. It schedules an arbitrary number of goroutines onto an arbitrary number of OS threads. You get quick context switches and you take advantage of all the cores in your system. The main disadvantage of this approach is the complexity it adds to the scheduler.

为了完成调度任务,Go scheduler使用3个主要的实体:

![](http://www.anger6.com/wp-content/uploads/2019/08/our-cast.jpg)

The triangle represents an OS thread. It's the thread of execution managed by the OS and works pretty much like your standard POSIX thread. In the runtime code, it's called **M** for machine.

The circle represents a goroutine. It includes the stack, the instruction pointer and other information important for scheduling goroutines, like any channel it might be blocked on. In the runtime code, it's called a **G**.

The rectangle represents a context for scheduling. You can look at it as a localized version of the scheduler which runs Go code on a single thread. It's the important part that lets us go from a N:1 scheduler to a M:N scheduler. In the runtime code, it's called **P** for processor. More on this part in a bit.

![](http://www.anger6.com/wp-content/uploads/2019/08/in-motion.jpg)

Here we see 2 threads (**M**), each holding a context (**P**), each running a goroutine (**G**). In order to run goroutines, a thread must hold a context.

The number of contexts is set on startup to the value of the `GOMAXPROCS` environment variable or through the runtime function `GOMAXPROCS()`. Normally this doesn't change during execution of your program. The fact that the number of contexts is fixed means that only `GOMAXPROCS` are running Go code at any point. We can use that to tune the invocation of the Go process to the individual computer, such at a 4 core PC is running Go code on 4 threads.

The greyed out goroutines are not running, but ready to be scheduled. They're arranged in lists called runqueues. Goroutines are added to the end of a runqueue whenever a goroutine executes a `go` statement. Once a context has run a goroutine until a scheduling point, it pops a goroutine off its runqueue, sets stack and instruction pointer and begins running the goroutine.

To bring down mutex contention, each context has its own local runqueue. A previous version of the Go scheduler only had a global runqueue with a mutex protecting it. Threads were often blocked waiting for the mutex to unlocked. This got really bad when you had 32 core machines that you wanted to squeeze as much performance out of as possible.

The scheduler keeps on scheduling in this steady state as long as all contexts have goroutines to run. However, there are a couple of scenarios that can change that.

# Who you gonna (sys)call?

You might wonder now, why have contexts at all? Can't we just put the runqueues on the threads and get rid of contexts? Not really. The reason we have contexts is so that we can hand them off to other threads if the running thread needs to block for some reason.

An example of when we need to block, is when we call into a syscall. Since a thread cannot both be executing code and be blocked on a syscall, we need to hand off the context so it can keep scheduling.

![](http://www.anger6.com/wp-content/uploads/2019/08/syscall.jpg)

Here we see a thread giving up its context so that another thread can run it. The scheduler makes sure there are enough threads to run all contexts. **M1** in the illustration above might be created just for the purpose of handling this syscall or it could come from a thread cache. The syscalling thread will hold on to the goroutine that made the syscall since it's technically still executing, albeit blocked in the OS.

When the syscall returns, the thread must try and get a context in order to run the returning goroutine. The normal mode of operation is to steal a context from one of the other threads. If it can't steal one, it will put the goroutine on a global runqueue, put itself on the thread cache and go to sleep.

The global runqueue is a runqueue that contexts pull from when they run out of their local runqueue. Contexts also periodically check the global runqueue for goroutines. Otherwise the goroutines on global runqueue could end up never running because of starvation.

This handling of syscalls is why Go programs run with multiple threads, even when `GOMAXPROCS` is 1. The runtime uses goroutines that call syscalls, leaving threads behind.

# Stealing work

Another way that the steady state of the system can change is when a context runs out of goroutines to schedule to. This can happen if the amount of work on the contexts' runqueues is unbalanced. This can cause a context to end up exhausting it's runqueue while there is still work to be done in the system. To keep running Go code, a context can take goroutines out of the global runqueue but if there are no goroutines in it, it'll have to get them from somewhere else.

![](http://www.anger6.com/wp-content/uploads/2019/08/steal.jpg)

hat somewhere is the other contexts. When a context runs out, it will try to steal about half of the runqueue from another context. This makes sure there is always work to do on each of the contexts, which in turn makes sure that all threads are working at their maximum capacity.

# Where to go?

There are many more details to the scheduler, like cgo threads, the `LockOSThread()`function and integration with the network poller. These are outside the scope of this post, but still merit study. I might write about these later. There are certainly plenty of interesting constructions to be found in the Go runtime library.

原文链接

_By Daniel Morsing_

[https://morsmachine.dk/go-scheduler](https://morsmachine.dk/go-scheduler)
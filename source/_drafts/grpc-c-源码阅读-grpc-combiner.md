---
title: grpc c++源码阅读----grpc_combiner
tags: []
id: '1537'
categories:
  - - my_tutorials
    - gRPC
  - - 我的教程
---

看代码有时候只能知道HOW的问题，而这里主要讲WHY的问题。也就是为什么要设计这么一个玩意儿出来？

典型执行临界区的代码如下:

mu.lock()  
do\_stuff()  
mu.unlock()

加锁---》执行代码---》解锁

也可以用下面的方式：

class combiner {  
run(f) {  
mu.lock()  
f()  
mu.unlock()  
}  
mutex mu;  
}

combiner.run(do\_stuff)

如果你在2个线程中同时调用combiner,那么需要使用一个队列。代码将其称为`combiner`是因为你一次传递了多个do\_stuff，它们将会在同一个锁中执行。

上面描述的实现存在的问题是：你会阻塞线程一段时间。这是有害的，因为你阻塞的是应用线程。

作为代替方案：

```
class combiner {
  mpscq q; // multi-producer single-consumer queue can be made non-blocking
  state s; // is it empty or executing

  run(f) {
    if (q.push(f)) { 
      // q.push returns true if it's the first thing
      while (q.pop(&f)) { // modulo some extra work to avoid races
        f();
      }
    }
  }
}
```
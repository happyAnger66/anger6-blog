---
title: 深入了解grpc_combiner
tags: []
id: '2064'
categories:
  - - uncategorized
---

grpc\_combiner是grpc c++代码里提供的一种抽象，代码流程里经常看到使用它。因此了解其原理能够帮我们更好地阅读源码。

Why?

先来看为什么的问题，即创建这个抽象的目的是什么？

主要是为了以下几个目的：

*   按顺序串行执行一些函数

*   非阻塞，即不阻塞当前线程

*   可以在其它线程上执行

*   异步，即调用可以立即返回

通常我们通过以下方式执行临界区代码：

```
mu.lock()
do_stuff()
mu.unlock()
```

或者通过以下方式：

```
class combiner {
  run(f) {
    mu.lock()
    f()
    mu.unlock()
  }
  mutex mu;
}

combiner.run(do_stuff)
```

如果你有2个线程要同时调用同一个combiner,那么我们需要再加一个队列。由于你可以向其同时传递多个do\_stuff,因此这个结构叫做"combiner"。

上面的实现有一个明显的问题：那就是会阻塞当前的线程（如当前锁不可用），这对于应用来说是有害的,尤其是grpc这种使用reactor模式提高性能的代码。因此需要解决。

考虑如下的实现方式：

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

基本的思路是当第一个任务加入到combiner时，循环遍历队列顺序执行队列中的任务。

grpc使用combiner的一个典型用处是处理批量写操作.

combiner还提供了另外一个层次的调用接口`run_finall`y.通过此接口运行的任务会在队列中的所有任务运行完后运行。这意味着有另外一个最后运行队列，combiner会尽大努力保证最后运行这些任务。在处理最后任务的过程中，可能又加入了新的任务，因此需要再次尝试从队列中取出任务。

`chttp2`在运行状态下运行所有的操作，除非它看到run\_finally了一个写操作。这样放入combiner中的其它内容都可以添加到该写入操作中.

```
class combiner {
  mpscq q; // multi-producer single-consumer queue can be made non-blocking
  state s; // is it empty or executing
  queue finally; // you can only do run_finally when you are already running something from the combiner

  run(f) {
    if (q.push(f)) { 
      // q.push returns true if it's the first thing
      loop:
      while (q.pop(&f)) { // modulo some extra work to avoid races
        f();
      }
      while (finally.pop(&f)) {
        f();
      }
      goto loop;
    }
  }
}
```
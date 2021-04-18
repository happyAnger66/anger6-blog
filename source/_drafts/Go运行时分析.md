---
title: Go运行时分析
tags: []
id: '1866'
categories:
  - - program_language
    - Golang
  - - my_tutorials
    - Golang语言入门
  - - 我的教程
  - - 编程语言
---

简介

Go语言的计算模型是基于通信顺序模型(CSP).CSP最早发表于1978年的一篇论文。

Go是一门高级编程语言，其中包含了许多Hoare论文里提到的结构，这些在C语言里没有，这些结构比起使用锁和信号量来保护共享内存要容易理解的多。The model of computation used by the Go language is based upon the idea of communicating sequential processes put forth by C.A.R. Hoare in his seminal paper published in 1978 \[10\]. Go is a high level language with many of the constructs proposed in Hoare’s paper, which are not found in the C family of languages, and are easier to reason about than locks and semaphores protecting shared memory. Go provides support for concurrency through goroutines, which are extremely lightweight in comparison to threads, but can also execute independently. These goroutines communicate through a construct known as channels, which are essentially synchronized message queues. The use of channels for communication, as well as first class support for closures, are powerful tools that can be utilized to solve complex problems in a straightforward manner. Go is a relatively young language, and its first st
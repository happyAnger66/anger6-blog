---
title: Boost.Asio看这一篇就够了
tags: []
id: '6001'
categories:
  - - - 编程语言
    - - cpp
      - boost
date: 2019-06-29 04:36:53
---

# Boost.Asio


## 背景

大部分程序都需要与外界交互,可能通过文件、网络、或者串口.有时候,网络通信,单个i/o操作需要很长时间完成.这给应用程序开发带来了特殊的挑战.

Boost.Asio提供了管理这些耗时操作的工具,而不需要开发人员使用基于传统线程和显式锁的并发模型.

## 核心概念和功能

### Boost.Asio剖析
Boost.Asio可用来执行同步和异步操作,如socket上的i/o操作.接下来,我们通过一系列概念图来理解Boost.Asio是如何工作的.

##### 先来看一下执行同步连接时发生的操作:

![同步操作](../../images/boost-asio/sync_op.png)

你的程序需要至少有一个`io执行context`,它是一个`boost::asio::io_context`、`boost::asio::thread_pool`、或`boost::asio::system_context`对象.这个`io执行context`将作为代理连接操作系统提供的`io服务`.

```cpp
boost::asio::io_context io_context;
```

然后你的程序需要一个类似`tcp socket`的i/o对象来执行`i/o`操作.

```cpp
boost::asio::ip::tcp::socket socket(io_context);
```

##### 同步操作
执行了同步连接操作之后,下列事件会依次发生:

1. 调用`i/o对象`初始化连接操作

```cpp
socket.connect(server_endpoint);
```

2. `i/o`对象将操作交给`i/o执行context`
3. `i/o执行context`调用操作系统接口执行连接操作
4. 操作系统将`i/o`执行结果返回给`i/o执行context`
5. `i/o执行context`将操作的错误信息转换为`boost::system::error_code`. `error_code`可与特定值进行比较,或者测试其真值(`false`意味着没有错误).然后将执行结果传递回`i/o`对象.
6. 如果操作失败,`i/o`对象抛出`boost::system::system_error`异常.如果操作以下面接口调用:

```cpp
boost::system::error_code ec;
socket.connect(server_endpoint, ec);
```

那么则不会抛出异常,并且`ec`被设置为操作结果.


##### 异步操作

执行了`异步操作`之后,将发生以下事件:

![异步操作1](../../images/boost-asio/async_op1.png)

1. 调用`i/o对象`初始化连接操作

```cpp
socket.async_connect(server_endpoint, your_completion_handler);
```

其中`your_completion_handler`有以下签名:

```cpp
void your_completion_handler(const boost::system::error_code& ec);
```

执行不同异步操作的完成函数有不同的签名.

2. `i/o`对象将操作交给`i/o执行context`
3. `i/o执行context`通知操作系统需要执行异步连接.
时间流逝(在同步操作里,这个时间包含连接操作的全部时间).

![异步操作2](../../images/boost-asio/async_op2.png)

4. 操作系统通过将执行结果放入一个队列来指示操作完成.这个结果可以被`i/o执行context`取出.
5. 当使用`io_context`作为`i/o执行context`时,你的程序必须调用`io_context::run`(或者其它类似的成员函数)以检索结果.`io_context::run`在有未完成的异步操作时会一直阻塞,所以你可以在开始第一个异步操作后就调用它.
6. 在`io_context::run`内部,`i/o执行context`获取操作结果,将其转化为`error_code`,然后传递给异步完成回调函数.


### `Proactor`模式:不使用线程的并发模型

##### `Proactor`和`Boost`

![Proactor](../../images/boost-asio/proactor.png)

##### `Proactor模式`:

+ 异步操作

定义异步操作,比如:在socket上异步读或异步写.

+ 异步操作处理器

执行异步操作并在操作完成时向异步事件完成队列中存入完成事件.从高层视角来看,内部服务如`reactive_socket_service`是异步操作处理器

+ 事件完成队列

缓存完成事件,直到异步事件分发器从中取出事件.

+ 完成处理句柄

处理异步操作结果.这些函数对象通常使用`boost::bind`创建.

+ 异步事件解复用器

阻塞直到完成事件队列有事件,然后将完成事件传递给调用者.

+ Proactor

调用异步事件解复用器来读取事件,然后将其分发给相关事件的完成处理句柄(比如.调用函数对象).这是`io_context`类所代表的抽象.

+ 初始化

应用程序通过高层的接口如`basic_stream_socket`启动特定的异步操作,这个接口将其代理给`reactive_socket_service`.

##### 使用`Reactor`来实现

在很多平台上,`Boost.Asio`实现根据`Reactor`来实现`Proactor`模式,比如`select`,`epoll`或`kqueue`

+ 异步操作处理

`reactor`使用`select`,`epoll`或者`kqueue`实现.当`reactor`表明资源已经就绪,处理器执行异步操作并将相关的完成处理函数加入完成事件队列中.

+ 完成事件队列

完成处理句柄(如函数对象)的链表.

+ 异步事件分发器

通过事件或条件变量在完成事件队列上等待完成句柄可用.

##### 在windows上使用`overlapped I/O`

##### 优势

+ 可移植
+ 并发与线程解耦
+ 高性能和可扩展
+ 简化同步
+ 功能组合

#### 劣势

+ 编程复杂
+ 内存使用

### 线程和`Boost.Asio`

##### 线程安全

通常,并发地使用不同对象是安全的,但是并发地使用同一个对象是不安全的.但是,`io_context`提供了强保证,并发地使用其单一对象是安全的.

##### 线程池

多个线程可能调用`io_context::run`来使用线程池执行完成事件.
注意所有调用`io_context::run`的线程是等价的,`io_context`可能以任意顺序分配任务.

##### 内部线程

库的内部实现可能使用内部线程来模拟异步.这些线程应该尽可能地对用户不可见.另外,这些线程必须:

+ 不能直接调用用户代码
+ 必须阻塞所有信号

这一方法得到了以下保证:

+ 异步完成处理函数只在调用`io_context::run`的线程中调用


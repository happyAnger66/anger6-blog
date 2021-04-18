---
title: Celery源码分析（三）---------Blueprint
tags: []
id: '68'
categories:
  - - 分布式系统
    - Celery
date: 2019-05-12 10:39:35
---

上一节讲到任务执行单元Worker主要维护了一个Blueprint对象，Worker的启动主要就是启动Blueprint对象，这一节我们来详细看下Blueprint.

首先，还是先看下时序流程图：

![](/images/wp-content/uploads/2019/05/c3-1024x876.jpg)

结合时序图进行分析:

1.在Worker调用setup_instance时会构造Blueprint，这个Blueprint是个内部类，里面定义了其default_steps.

```python
class Blueprint(bootsteps.Blueprint):  
"""Worker bootstep blueprint."""  
name = 'Worker'  
default_steps = set([  
'celery.worker.components:Hub',  
'celery.worker.components:Queues',  
'celery.worker.components:Pool',  
'celery.worker.components:Beat',  
'celery.worker.components:Timer',  
'celery.worker.components:StateDB',  
'celery.worker.components:Consumer',  
'celery.worker.autoscale:WorkerComponent',  
'celery.worker.autoreload:WorkerComponent',

```

在Blueprint的构造函数里，主要代码就是构造自己的steps,如果构造函数传递了steps参数就用参数，否则就用default_steps.  
Worker在构造时没有传递steps,因此就是用的default_steps.

2.构造完Blueprint后，调用其apply方法。apply方法主要完成2个工作：

a.调用_finalize_steps分析各个step间的依赖关系并构造出一个有向无环的图。然后根据依赖关系构造各个step.

b.然后调用step的include方法，这个方法是判断step是否需要包含进app对象中，默认是包含。如果step不需要包含进app,需要自已实现include_if方法。

如果step要包含进app,则会调用step的create方法，这个方法主要用于不同的step创建自己所需要的特定对象，这个对象在后面启动step时还会调用其start方法。

3.启动Worker时调用Blueprint的start方法，然后依次调用step的start方法。

step如果自己实现了start方法则调用自己的实现，否则默认实现就是调用2.b中创建的对象的start方法。

## 这样就分析了Worker是如何通过Blueprint启动自已的。

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/53890071  
版权声明：本文为博主原创文章，转载请附上博文链接！

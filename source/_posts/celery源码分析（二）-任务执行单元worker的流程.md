---
title: Celery源码分析（二）--------任务执行单元Worker的流程
tags: []
id: '65'
categories:
  - - 分布式系统
    - Celery
date: 2019-05-12 10:38:40
---

上一节中讲到通过命令行构造"celery.apps.worker::Worker"对象，然后就调用Worker对象的start方法启动Worker.

因此，这个Worker对象是一个核心对象，下面着重对其分析。

下面是Worker对象构造函数和start函数的时序图，对照流程图分析：

![](http://www.anger6.com/wp-content/uploads/2019/05/c2-700x1024.jpg)

1.首先，调用AppLoader的init_worker方法，这个方法主要是根据配置加载一些需要的模块。

2.然后是on_before_init,这个主要是调用trace模块的setup_worker_optimizations方法。

这个方法主要做3件事:

a.为"BaseTask"安装栈保护。其实就是对call方法打个补丁。

b.然后调用Celery的'set_current'方法设置当前的app对象。

c.最后调用Celery的finalize方法，绑定所有的task任务到app对象。（包括系统自带的和我们自己编写的任务）

3.调用setup_defaults方法设置一些参数的默认值。

4.调用setup_instance方法初始化一些对象，主要做以下事情：

a.调用setup_queues，分别通过select,deselect设置amqp关注和不关注的队列，如果配置了CELERY_WORK_DIRECT，则通过调用select_add向关注队列中添加对应的队列。我们知道celery默认使用amqp协议的rabbitMQ做为broker.

b.调用setup_includes安装一些通过'CELERY_INCLUDE'配置的模块,保证所有的任务模块都导入了。

c.创建一个Blueprint对象，这个对象比较重要，从名字上来看是蓝图的意思，它会包含许多步骤对象，这些步骤之间通过有向无环图来建立依赖关系，用于根据依赖关系依次调用。后面还会专门分析。

我们先看一下Worker的Blueprint中都包含哪些步骤:

```python
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

]) 
```

d.调用Blueprint的apply方法。完成Blueprint中每个步骤对象的构造和初始化。

5.调用Worker的start方法，这个方法主要是调用Blueprint的start方法启动Blueprint.

## 这样就分析完了Worker对象的构造和start方法，下一节将会对Blueprint做详细分析。

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/53873589  
版权声明：本文为博主原创文章，转载请附上博文链接！

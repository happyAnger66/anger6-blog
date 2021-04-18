---
title: linux epoll实现分析
tags: []
id: '1682'
categories:
  - - Linux
  - - linux
    - 内核活动
  - - 系统架构
  - - system_arch
    - 高并发
date: 2019-07-27 15:03:17
---

epoll的作用是进行I/O的多路复用，可以同时监听多个fd产生的事件。常结合异步处理实现单线程的高并发。在多核环境中，可以结合多线程实现负载分担。

本文主要分析一下linux epoll的实现。

## API

### epoll_create(int size);.

### epoll_create1(int flags);

创建一个epoll实例,并返回与之关联的一个fd.这是后面我们继续使用epoll其它接口的基础。

从内核2.6.27开始，加入了一个新的epoll_create1,这个函数有一个flag参数，可以指定EPOLL_CLOEXEC标志，标识在exec之后关闭fd.

来看下创建epoll都做了些什么？

创建一个struct eventpoll结构，每个epoll使用这个结构管理其监听的所有fd以及就绪fd.

然后创建一个epoll对应的file结构，并将创建的epoll挂到file的private_data上（这是vfs的通常做法，将具体文件相关的结构挂到私有数据上）.

将file结构的f_op填充为epoll实现eventpoll_fops.这样利用多态实现以后对epoll文件file的相关操作就能映射到epoll具体的实现上。

### int epoll_ctl(int epfd, int op, int fd, struct epoll_event *event);

typedef union epoll_data {  
void *ptr;  
int fd;  
uint32_t u32;  
uint64_t u64;  
} epoll_data_t;

```
       struct epoll_event {
           uint32_t     events;      /* Epoll events */
           epoll_data_t data;        /* User data variable */
       };
```

通过此接口将我们关心的fd和相应的事件添加/删除/修改到epoll中。

通过event结构里的events添加我们关注的事件类型。

其中的events可以指定如下一些标志:

*   EPOLLIN:监听文件可读

*   EPOLLOUT:监听文件可写

*   EPOLLRDHUP(2.6.17添加):对于基于流的socket,标识对端关闭或者shutdown半关闭了连接（对端只读不写)。对于shutdown这种情况，这个标志在使用边缘触发模式进行写操作时能够有效地监控对端是否关闭了写端。关于EPOLL的水平触发和边缘触发模式后面会有详细介绍。

*   EPOLLPRI：有urgent data到达

*   EPOLLERR：这个事件epoll操作会始终进行监听，不管你有没有显式地指定

*   EPOLLHUP：epoll也会始终监听这个事件.标识对端关闭，当通道上剩余数据读完后，read会返回0.

*   EPOLLLET:使用边缘触发模式（ET)，epoll默认采用水平触发模式（LT）

为了说明LT和ET的区别，考虑下面的场景：

*   有一代表pipe读端的rfd注册到epoll上。

*   pipe另一端写入了2KB的数据

*   epoll_wait返回标识rfd有数据可读

*   调用read从rfd读取了1KB的数据

*   epoll_wait结束

对于ET模式，epoll_wait结束后，剩余的1KB数据有可能仍然保存在文件的buffer中，同时对端等待这些数据读取后的响应。这是因为ET模式仅仅在监控的文件发生变化时提交事件，因此epoll_wait继续等待有新的数据到来才能触发新的事件。

使用ET模式的程序应该使用非阻塞模式，这样不至于因为阻塞导致同时监听的其它fd被饿死。建议使用ET模式按照如下方式：

*   使用非阻塞fd

*   持续调用read或者write直到返回EAGAIN再继续等待新事件(epoll_wait).

相对的，使用LT模式（默认模式），epoll就相当于是一个更快的poll,它可以代替任何使用poll的地方，因为具有和poll相同的语义。对于上面的例子，只要buffer中还有未读的数据,epoll_wait就会一直通知事件。

*   EPOLLONESHOT（linux 2.6.2)

这个选项用的比较少，作用是当关心的fd上产生事件时，epoll将会停止关注和上报fd后续的事件，我们需要在处理完事件后再调用epoll_ctl重新安装关心的事件。我能想到这个选项的作用可能是在使用ET模式时提高效率，比如我们在读数据时，又有新数据到来，可以一直读取完而不用再产生和关注新事件。

*   EPOLLWAKEUP(linux 3.5)

这个选项很罕见，简单介绍下。当linux运行于autosleep模式时，当有事件产生时将设备从sleep状态唤醒，设备驱动在事件入队之后就继续sleep.如果要让设备等事件处理后再进行sleep状态就要设备此标志。

*   EPOLLEXCLUSIVE(linux 4.5)

设置独占唤醒模式。这个标志主要用在我们用多个epoll监听同一个fd时，保证当事件到来时只唤醒其中一个epoll.这个标志默认不会设置，因此会有“惊群效应”

如果多个epoll监听同一个fd,部分设置了此选项，部分没有设置此选项。那么到事件到来时，所有未设置此选项的epoll都会唤醒，设置此选项的至少唤醒一个。

这个选项只能在EPOLL_CTL_ADD时使用，EPOLL_CTL_MOD使用会报错。

### int epoll_wait(int epfd, struct epoll_event *events, int maxevents, int timeout)

开始事件循环.

events参数会输出就绪的文件及其事件。

返回值标识就绪的events的个数。

maxevents指明最多返回多少个就绪事件，必须大于0

timeout指定等待超时时间，单位ms.-1标识不超时。

epoll_wait在以下情况会返回:

*   监听的某个文件递交了一个事件

*   被信号打断

*   超时

### epoll__ctl实现

来看一下向epoll中添加fd的流程。我们只分析添加的fd是普通文件的情况，因为可以向epoll中可以添加另一个epoll,这种情况的代码暂不分析。

epoll内部使用红黑树管理fd,在管理大量fd时效率依然很高。

添加fd的关键在于建立目标文件与epoll的关联，当fd产生事件时能够通知关心的epoll.

主要代码在ep_insert中，其中涉及的数据结构比较多，先上一张总图

![](http://www.anger6.com/wp-content/uploads/2019/07/image-21-1024x637.png)

对于每个fd都会关联一个epitem,这里面的ffd包含了实际的file,fd.

ep_queue结构的作用是在ep_ptable_queue_proc函数和epitem之间建立联系，调用ep_ptable_queue_proc的时候就能将epitem的等待队列加入到目标文件的等待队列中。

不同的文件系统要支持epoll,要实现file_operations的__poll_t (*poll) (struct file *, struct poll_table_struct *)接口。

通过调用对应文件的poll函数将epitem加入到目标文件的等待队列中。这个函数有2个参数，一个是目标文件，一个是poll_table结构。我们来看一个具体文件系统实现的poll函数，来了解下具体流程。

以fuse文件系统为例:

__poll_t fuse_file_poll(struct file *file, poll_table *wait)

poll_wait(file, &ff->poll_wait, wait);

调用poll_wait函数，ff->poll_wait即为目标文件的等待队列头。poll_wait函数是linux提供的函数，用于具体文件系统在poll中调用。这个函数调用poll_table里的_qproc函数，即上文提到的"ep_ptable_queue_proc".

ep_ptable_queue_proc完成目标文件和epitem关系的建立。

static void ep_ptable_queue_proc(struct file *file, wait_queue_head_t *whead,  
poll_table *pt)  
{  
struct epitem *epi = ep_item_from_epqueue(pt);  
struct eppoll_entry *pwq;

```
if (epi->nwait >= 0 && (pwq = kmem_cache_alloc(pwq_cache, GFP_KERNEL))) {
    init_waitqueue_func_entry(&pwq->wait, ep_poll_callback);
    pwq->whead = whead;
    pwq->base = epi;
    if (epi->event.events & EPOLLEXCLUSIVE)
        add_wait_queue_exclusive(whead, &pwq->wait);
    else
        add_wait_queue(whead, &pwq->wait);
    list_add_tail(&pwq->llink, &epi->pwqlist);
    epi->nwait++;
} else {
    /* We have to signal that an error occurred */
    epi->nwait = -1;
}
```

这个函数3个参数分别为目标文件，目标文件等待队列头，和poll_table.

通过poll_table找到对应的epitem,然后创建一个epoll_entry对象。

在这个对象上创建等待队列项pwq->wait,并设置回调ep_poll_callback.

调用add_wait_queue加入到目标文件中。

这里可以看到，如果对应的fd的events设置了前面提到的EPOLLEXCLUSIVE选项，则以独占方式加入到目标文件的完成队列中。

通过这一步操作，就建立了目标文件和对应fd epoll的关联，到目标文件有事件到来时，就能够调用ep_poll_callback函数通知epoll了。

### 事件递交流程

我们再来看一下，当有实际事件就绪时，通知epoll的流程。

其实上文已经提到了，会调用上面注册的ep_poll_callback函数。

主要代码如下:

首先判断对应fd的epi是否在就绪链表里，如果没有则添加到就绪链表尾。

if (!ep_is_linked(&epi->rdllink)) {  
list_add_tail(&epi->rdllink, &ep->rdllist);  
ep_pm_stay_awake_rcu(epi);  
}

然后，判断是否有进程在epoll_wait,如果有则唤醒。

if (waitqueue_active(&ep->wq)) {  
if ((epi->event.events & EPOLLEXCLUSIVE) &&  
!(pollflags & POLLFREE)) {  
switch (pollflags & EPOLLINOUT_BITS) {  
case EPOLLIN:  
if (epi->event.events & EPOLLIN)  
ewake = 1;  
break;  
case EPOLLOUT:  
if (epi->event.events & EPOLLOUT)  
ewake = 1;  
break;  
case 0:  
ewake = 1;  
break;  
}  
}  
wake_up_locked(&ep->wq);  
}  
if (waitqueue_active(&ep->poll_wait))  
pwake++;

我们可以看到，如果有多个fd同时就绪，会以通知epoll的先后顺序添加到就绪队列中。

### epoll_wait处理就绪事件

就绪事件已经添加到链表中，则就差epoll_wait获取并返回了。

主要是调用"ep_send_events"发送就绪事件

先将就绪链表放到一个临时链表中，然后发送事件。由于访问epoll的就绪链表需要自旋锁，因此这里通过临时链表来尽快释放自旋锁。

spin_lock_irqsave(&ep->lock, flags);  
list_splice_init(&ep->rdllist, &txlist);  
ep->ovflist = NULL;  
spin_unlock_irqrestore(&ep->lock, flags);

发送事件主要是遍历临时链表，并将就绪事件拷贝到用户态。这里还有一个注意点，就是对应LT模式的处理:

else if (!(epi->event.events & EPOLLET)) {  
/*  
* If this file has been added with Level  
* Trigger mode, we need to insert back inside  
* the ready list, so that the next call to  
* epoll_wait() will check again the events  
* availability. At this point, no one can insert  
* into ep->rdllist besides us. The epoll_ctl()  
* callers are locked out by  
* ep_scan_ready_list() holding "mtx" and the  
* poll callback will queue them in ep->ovflist.  
*/  
list_add_tail(&epi->rdllink, &ep->rdllist);  
ep_pm_stay_awake(epi);  
}

如果对应fd的epi是LT模式，则将其再次加入就绪链表，这样下次调用epoll_wait时就会再次检查是否还有事件可用。

好了，epoll的实现就分析完成了。通过学习你能回答下面几个问题吗？

*   如果2个线程同时监听同一个epoll fd,那么有fd就绪时会都唤醒吗？

*   如果2个线程使用不同的epoll fd,但是加入了相同的fd,又会是怎样的呢？
*   如果同时有多个fd有就绪事件，epoll_wait返回的就绪events数组里顺序如何?
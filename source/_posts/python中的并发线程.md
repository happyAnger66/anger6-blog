---
title: python中的并发线程
tags: []
id: '2094'
categories:
  - - 编程语言
  - - python
date: 2020-02-23 03:40:38
---

python也提供了线程相关的并发原语，如锁threading.Lock，事件threading.Event，条件变量threading.Condition。

本质上都是对pthread_mutex_t, pthread_condition_t的封装。

本篇文章通过2个例子来分析理解python中如何控制并发。

1.实现2个线程交替打印

2.实现一个支持并发的环形队列

代码1：2个线程交替打印:

​  
import threading  
import time

c1 = threading.Condition() #用2个条件变量控制交替执行  
c2 = threading.Condition()

def prt(i, wait, notify, name):  
while True:  
with wait:  
wait.wait()  
print(i, name)  
i += 2  
time.sleep(1)  
with notify:  
notify.notify_all()

t1 = threading.Thread(target=prt, args=(0, c1, c2, "thread1", )) #等待通知交替传递  
t2 = threading.Thread(target=prt, args=(1, c2, c1, "thread2", ))

t1.start()  
t2.start()

with c1: #选择一个线程先运行  
c1.notify_all()

t1.join()  
t2.join()

​  
代码2：一个支持并发的环形队列实现

import threading

class RingQueue:  
def init(self, maxsize):  
self._maxsize = maxsize self._tail = 0 self._head = 0 self._len = 0 self._queue = [None for_ in range(maxsize)]  
self._mutex = threading.Lock() #控制并发访问的线程锁  
self.not_full = threading.Condition(self._mutex) #等待队列有空闲位置  
self.not_empty = threading.Condition(self._mutex) #等待队列有数据

```
def put(self, item):
    with self.not_full:
        while self._len == self._maxsize:
            self.not_full.wait()

        i = self._tail
        self._queue[i] = item
        self._tail = (self._tail + 1 ) % self._maxsize
        if self._len == 0:        #当前队列为空，则尝试唤醒可能的消费者
            self.not_empty.notify()    
        self._len += 1
    return i

def get(self):
    with self.not_empty:
        while self._len == 0:
            self.not_empty.wait()
        i = self._head
        data = self._queue[self._head]
        self._head = (self._head + 1) % self._maxsize
        if self._len == self._maxsize: #如果队列满，则唤醒可能的生产者
            self.not_full.notify()
        self._len -= 1
    return i
```

def producer(q):  
while True:  
for i in range(10000):  
print('put', q.put(i))

def consumer(q):  
while True:  
print('get', q.get())

q = RingQueue(10)  
t1 = threading.Thread(target=producer, args=(q,))  
t2 = threading.Thread(target=consumer, args=(q,))

t1.start()  
t2.start()

t1.join()  
t2.join()  
 

我们再考虑为上面的队列加入以下需求：

1.我们想知道队列中的所有任务都被消费了，通常在关闭清除队列时需要知道。

我们可以通过在队列中加入另一个条件变量来实现

self.all_tasks_done = threading.Condition(self.mutex)  
self.unfinished_tasks = 0  
注意，这个新的条件变量和之前用于协调队列长度的锁是同一把锁。

然后增加下面2个方法：

def task_done(self):  
'''  
  当我们从队列中取出一个任务，并处理完成后调用这个方法.  
通常消费者在调用get()并完成任务后调用，用于通知正在处理的任务完成.  
如果当前有一个阻塞的join调用，那么当所有任务处理完成后，会解除阻塞.  
在调用次数超过队列条目数量时抛出异常.  
'''  
with self.all_tasks_done:  
unfinished = self.unfinished_tasks - 1  
if unfinished <= 0:  
if unfinished < 0:  
raise ValueError('task_done() called too many times')  
self.all_tasks_done.notify_all()  
self.unfinished_tasks = unfinished

def join(self):  
'''阻塞到队列中的所有条目都被处理完成.  
'''  
with self.all_tasks_done:  
while self.unfinished_tasks:  
self.all_tasks_done.wait()  
然后我们再修改put方法，每加一个任务都对unfinished_tasks进行加1.

def put(self, item):  
with self.not_full:  
while self._len == self._maxsize:  
self.not_full.wait()

```
        i = self._tail
        self._queue[i] = item
        self._tail = (self._tail + 1 ) % self._maxsize
        self.unfinished_tasks += 1 #有任务加入
        if self._len == 0:        #当前队列为空，则尝试唤醒可能的消费者
            self.not_empty.notify()    
        self._len += 1
    return i
```

   
————————————————  
版权声明：本文为CSDN博主「self-motivation」的原创文章，遵循 CC 4.0 BY-SA 版权协议，转载请附上原文出处链接及本声明。  
原文链接：https://blog.csdn.net/happyAnger6/article/details/104452063
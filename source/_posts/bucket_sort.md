---
title: 排序----桶排序
tags: []
id: '964'
categories:
  - - self_culture
    - 数据结构与算法
    - 极客时间----数据结构与算法之美(王争)
  - - 自我修养
date: 2019-07-09 12:49:08
---

```python
def partition(a, start, end):  
   tmp = a[end]  
   k = start  
   for i in range(start, end):  
      if a[i] < tmp:  
         a[k], a[i] = a[i], a[k]  
         k += 1  
   a[k], a[end] = a[end], a[k]  
   return k  
  
def quick_sort(a, i, j):  
   if i >= j:  
      return  
 mid = partition(a, i, j)  
   quick_sort(a, i, mid-1)  
   quick_sort(a, mid+1, j)  
  
def buckets_sort(a, n):  
   start, end = min(a), max(a)  
   step = (end - start) // n  
   buckets = [ [] for _ in range(step) ]  
   for e in a:  
      buckets[(e-start)//step].append(e)  
  
   start, end = 0, 0  
   for bucket in buckets:  
      quick_sort(bucket, 0, len(bucket)-1)  
      end += len(bucket)  
      a[start:end+1] = bucket[:]  
      start += len(bucket)  
  
if __name__ == "__main__":  
   import random  
   l = [random.randrange(10000) for _ in range(1000)]  
   print(len(l), l)  
   buckets_sort(l, 5)  
   print(len(l), l
```
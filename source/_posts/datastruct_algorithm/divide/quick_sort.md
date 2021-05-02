---
title: 快速排序
tags: [算法, 分治法, divide, algorithm]
id: '1107'
categories:
    - 自我修养
    - 数据结构与算法
date: 2021-05-02 21:10:53
---

```python

def quick_sort(a):
    def quick_sort_array(a, i, j):
        if i >= j:
            return a

        def partition(a, i, j):
            pos = i
            while i < j:
                if a[i] < a[j]:
                    if i != pos:
                        a[pos], a[i] = a[i], a[pos]
                    pos += 1
                i += 1
            a[pos], a[j] = a[j], a[pos]
            return pos


        k = partition(a, i, j)
        quick_sort_array(a, i, k-1)
        quick_sort_array(a, k+1, j)
        return a
    
    return quick_sort_array(a, 0, len(a)-1)
        
if __name__ == "__main__":
    print(quick_sort([1, 3, 2, 4, 5, 8, 7, 0]))
    print(quick_sort([1]))
    print(quick_sort([]))
    print(quick_sort([9, 8, 7, 3, 2, 0]))
    print(quick_sort([9, 18, 27, 33, 42, 50]))
```

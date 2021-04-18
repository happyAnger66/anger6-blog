---
title: 求最大子数组---python实现
tags: []
id: '759'
categories:
  - - 自我修养
    - 数据结构与算法
date: 2019-06-29 14:17:51
---

动态规划求一个数组的最大子数组。

```python
def max_sub_array(a):  
    sum, max, start, new_start, end = a[0], a[0], 0, 0, 0  
    for i, e in enumerate(a[1:], 1):  
        if sum <= 0:  
            new_start = i  
            sum = e  
        else:  
            sum += e  
  
        if sum > max:  
            start = new_start  
            end = i  
            max = sum  
    return start, end, max
```
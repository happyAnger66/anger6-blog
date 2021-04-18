---
title: 求所有和等于n的组合
tags: []
id: '1231'
categories:
  - - 自我修养
    - 数据结构与算法
date: 2019-07-18 14:50:37
---

给一个数组n,求出所有和等于target的数字组合.

```python
def n_sum(n, target, cur=None, results=None):
    if results is None:
        results = []

    if cur is None:
        cur = []

    new_n = n[:]
    for i, e in enumerate(n):
        cur.append(e)
        if e == target:
            results.append(cur[:])
        new_n.remove(e)
        n_sum(new_n, target-e, cur, results)
        cur.pop()
    return results

使用剪枝回溯算法，上面的代码运算量太大，复杂度为O(2^n).
因此，我们先对数组排序，然后再计算:
total_counts = 0
def n_sum(n, target, cur=None, results=None):
    global total_counts
    if results is None:
        results = []

    if cur is None:
        cur = []

    new_n = n[:]
    for i, e in enumerate(n):
        cur.append(e)
        total_counts += 1
        if e == target:
            results.append(cur[:])
        elif e > target:
            cur.pop()
            continue n_sum(n[i+1:], target-e, cur, results)
        cur.pop()
    return results
```
---
title: 1.Two sum
tags: []
id: '753'
categories:
  - - 自我修养
    - leetcode_meet_me
date: 2019-06-29 04:36:53
---

给一个整数数组和一个整数target，返回数组中相加之和等于target的两个数

你可以认为对于给定的输入，只有一组解。同样的数字不能使用两次。

举例：

nums = [2, 7, 11, 15], target = 9,

nums[0] + nums[1] = 0

return [0,1].

```python
import random  
  
if __name__ == "__main__":  
    d = {}  
    r = [ random.randrange(100) for _ in range(100)]  
    a = list(set(r))  
    t = 100  
    for i, v in enumerate(a):  
        d[v] = i  
  
    for i, v in enumerate(a):  
        needed = t - v  
        try:  
            x = d[needed]  
            if x != i:  
                print(i, x, a[i], a[x])  
        except KeyError:  
            pass
```
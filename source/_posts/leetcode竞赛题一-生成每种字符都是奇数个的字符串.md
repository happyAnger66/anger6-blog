---
title: leetcode竞赛题(一)----生成每种字符都是奇数个的字符串
tags: []
id: '2096'
categories:
  - - self_culture
    - 2020刷题记录
date: 2020-03-08 07:07:10
---

有志同道合的朋友，可以大家一起交流监督学习。哈哈哈 ！！！

5352. 生成每种字符都是奇数个的字符串  
给你一个整数 n，请你返回一个含 n 个字符的字符串，其中每种字符在该字符串中都恰好出现 奇数次 。

返回的字符串必须只含小写英文字母。如果存在多个满足题目要求的字符串，则返回其中任意一个即可。

示例 1：

输入：n = 4  
输出："pppz"  
解释："pppz" 是一个满足题目要求的字符串，因为 'p' 出现 3 次，且 'z' 出现 1 次。当然，还有很多其他字符串也满足题目要求，比如："ohhh" 和 "love"。  
示例 2：

输入：n = 2  
输出："xy"  
解释："xy" 是一个满足题目要求的字符串，因为 'x' 和 'y' 各出现 1 次。当然，还有很多其他字符串也满足题目要求，比如："ag" 和 "ur"。  
示例 3：

输入：n = 7  
输出："holasss"  
 

提示：

1 <= n <= 500  
我的解答：

```
class Solution:
    def generateTheString(self, n: int) -> str:
        cur_len = 0
        states = [0 for _ in range(26)]
        i, bFind = 0, False
        while i <= 26 and i >= 0:
            if bFind:
                break
 
            if i == 26:
                i-= 1
                continue
 
            while True:
                if states[i] == 0:
                    j = 1
                else:
                    j = states[i] + 2
 
                cur_len-=states[i]
                if cur_len + j == n:
                    states[i] = j
                    bFind = True
                    break
                elif cur_len + j > n:
                    states[i] = 0
                    i-=1
                    break
                else:
                    states[i] = j
                    cur_len += j
                    i+=1
                    break
 
        s = ""
        cnt = 0
        for i, j in enumerate(states):
            if j > 0:
                c = chr(ord('a')+i)*j
                s += c
                cnt+=j

         return s
```
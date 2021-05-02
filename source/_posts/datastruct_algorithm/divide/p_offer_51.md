---
title: 求数组的逆序度
tags: [算法, 分治法, divide, algorithm]
id: '1106'
categories:
    - 自我修养
    - 数据结构与算法
date: 2021-05-02 21:10:53
---

```python

"""
Copyright (c) Huawei Technologies Co., Ltd. 2021-2021. All rights reserved.
Description: easy_ut p_offer_51 file
Author: zhangxiaoan 00565442
Create: 2021/4/28 17:38
"""
def merge_reverse(nums, i, j):
    if j < i:
        return [], 0

    if i == j:
        return nums[i:i+1], 0

    mid = i + (j-i)//2
    left, left_num = merge_reverse(nums, i, mid)
    right, right_num = merge_reverse(nums, mid+1, j)

    new_nums, reverse = [], 0
    n_left, n_right = len(left), len(right)
    n_i, n_j = 0, 0
    while n_i < n_left and n_j < n_right:
        if left[n_i] <= right[n_j]:
            new_nums.append(left[n_i])
            n_i += 1
        else:
            new_nums.append(right[n_j])
            reverse += n_left - n_i
            n_j += 1

    new_nums.extend(left[n_i:])
    new_nums.extend(right[n_j:])

    return new_nums, reverse + left_num + right_num


def reversePairs(nums):
    return merge_reverse(nums, 0, len(nums)-1)[1]

if __name__ == "__main__":
    print(reversePairs([7, 5, 6, 4]))
    print(reversePairs([1, 3, 2, 3, 1]))
```

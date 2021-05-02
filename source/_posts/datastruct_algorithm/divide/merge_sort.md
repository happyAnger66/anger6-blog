---
title: 归并排序
tags: [算法, 分治法, divide, algorithm]
id: '1105'
categories:
    - 自我修养
    - 数据结构与算法
date: 2021-05-02 21:10:53
---

```python

def merge_sort(a):
    def merge_array(a, i, j):
        if i >= j:
            return a

        def merge(a, left_i, left_j, right_i, right_j):
            new_a = []
            i, j = left_i, right_i
            while i <= left_j and j <= right_j:
                if a[i] <= a[j]:
                    pos = i
                    i += 1
                else:
                    pos = j
                    j += 1
                new_a.append(a[pos])

            if i <= left_j:
                new_a.extend(a[i:left_j+1])

            if right_i <= right_j:
                new_a.extend(a[j:right_j+1])

            a[left_i:right_j+1] = new_a

        mid = i + (j - i) // 2 
        merge_array(a, i, mid)
        merge_array(a, mid+1, j)
        merge(a, i, mid, mid+1, j)
        return a

    return merge_array(a, 0, len(a)-1)

if __name__ == "__main__":
    print(merge_sort([1, 3, 2, 4, 6, 8, 7]))
    print(merge_sort([8]))
    print(merge_sort([9, 8, 7, 4, 3, 2, 0]))
    print(merge_sort([9, 10, 12, 14, 13, 22, 100]))
```

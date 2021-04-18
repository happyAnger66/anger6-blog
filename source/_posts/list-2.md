---
title: list
tags: []
id: '1966'
categories:
  - - Python
  - - python
    - python入门教程
  - - python_basic
    - 数据模型
date: 2019-09-06 13:01:57
---

从本篇文章开始，将介绍python提供的常用数据结构。

这些数据结构从不同的维度可以进行不同的划分。

按可变性划分:

1.  可变数据结构:list,dict,set,bytearray
2.  不可变数据结构:tuple,string,bytes

按是否可以存储不同类型的元素划分:  

1.  扁平数据结构:string,bytes,bytearray  
    
2.  容器数据结构:list,dict,set,tuple

后面将会陆续从序列，集合，映射，可调用对象这4类来逐一介绍。

介绍list之前，我们有必要了解一下什么是序列.  

之所以称为序列，是因为它们都有以下特性.

*   能够通过索引来进行顺序访问
*   内置的函数len()可以返回序列的长度。如果序列的长度为n,则可以通过0....n-1来访问每个元素
*   支持切片操作a[i:j]  
    
*   序列类型有string,tuple,bytes,list,bytearray几种

好了，地基打好了，我们开始学习序列中的首个类型:list.  

list是python中最常用的数据结构之一.基本上可以将其看作是一个可以动态增长且使用方便的数组.

list是一种容器，可以容纳任意的python对象.

要使用list,首先我们需要创建list.  

构造list

1.  空list:[]  
    
2.  直接构造list:[1,2,3]  
    
3.  使用列表生成式:[ x for x in iterable]
4.  使用构造函数:list()或list(iterable)

关于4有几点说明:由iterable构造的list的元素顺序保持不变;可以使用任何可迭代对象来构造list,如list('abc')返回['a', 'b', 'c'],list((1,2,3))返回[1,2,3].如果不指定任何参数，则返回一个空list.

使用list  

list实现了序列具有的一些通用方法:

操作

结果

`x in s`

判断x是否在s中

`x not in s`

判断x是否不在s中

`s + t`

连接s,t

`s * n` or `n * s`

将s重复n次

`s[i]`

s中第i个元素,从0开始

`s[i:j]`

从i到j的切片  

`s[i:j:k]`

以k为步长,从i到j的切片

`len(s)`

s的长度

`min(s)`

s中的最小元素

`max(s)`

s中的最大元素

`s.index(x[, i[, j]])`

x在s中的第一个位置，可以指定特定的区间[i,j]

`s.count(x)`

s中x出现的次数

另外，作为可变序列类型，list还具有可变序列的一些通用方法  

Operation

Result

`s[i] = x`

设置第i个元素为x

`s[i:j] = t`

将i-j设置为可迭代的t

`del s[i:j]`

删除i到j,等价于 `s[i:j] = []`

`s[i:j:k] = t`

用可迭代的t替换i-j,步长为k

`del s[i:j:k]`

以k为步长删除i-j

`s.append(x)`

将x添加到s末尾,等价于 `s[len(s):len(s)] = [x]`

`s.clear()`

删除s中所有元素,等价于`del s[:]`

`s.copy()`

创建s的一个拷贝,等价于s[:]

`s.extend(t)` or `s += t`

用t扩充s,等价于`s[len(s):len(s)] = t`

`s *= n`

n个s

`s.insert(i, x)`

在位置i插入x.等价于`s[i:i] = [x]`

`s.pop([i])`

获取第i个元素并从s中m删除,不指定i则获取最后一个元素

`s.remove(x)`

从s中删除第一个等于x的元素

`s.reverse()`

就地翻转s

最后,list还支持以下方法:

*   `sort`(_*_, _key=None_, _reverse=None_)

就地排序list,默认使用'<'比较每个元素的大小。  

1.  key:我们可以通过关键字参数key来指定排序函数，这个排序函数接受一个元素，根据其返回值决定元素的大小。如果中途发生错误，sort不会抛出异常,list可能处于一个中间状态.python2中有许多比较函数使用2个参数，如locale.strcoll.为了方便地将2.x中的这些函数转换为key可以使用的函数，我们可以使用functools.cmp_to_key来将2.x中的函数进行包装.
2.  reverse:是为逆序.

值得注意的是sort排序是稳定的，意味着会保持2个相同元素的相对位置.  

其它

1.  关于列表表达式:列表表达式可以使代码更具表达力如得到每个字符的ascii值: [ord(x) for x in 'abc']
2.  将list当作栈LIFO来使用十分高效，通过append方法向list末尾添加元素，通过无参数的pop方法将最后一个元素弹出
3.  list不适合作为队列FIFO来使用，因为从头来添加和删除元素需要对剩余元素进行移动。此时应该使用适合在两头进行添加删除的collections.deque.

关于list,掌握这些已经相当够用了。
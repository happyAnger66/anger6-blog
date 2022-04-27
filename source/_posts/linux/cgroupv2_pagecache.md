---
title: Cgroup v2 and Page cache
tags: []
id: '824'
categories:
  - 操作系统
  - linux
  - cgroup
date: 2022-03-04 21:09:53
---

# Cgroup v2 and Page Cache

`cgroup`子系统是进行公平分配和限制系统资源的方式.它通过树形结构组织所有的数据,叶子节点  
依赖父节点并继承它们的设置.另外,`cgroup`提供很多资源的计数和统计.

`cgroup`控制无处不在.即使你没有显式的使用它们,它们在现代Linux发行版中也是默认打开并与`systemd`集成在一起.

## 概览

`cgroup`对理解`page cache`使用中有重要的意义.它还帮助调试问题和配置软件有更好的状态.  
比如,通过`cgroup`内存限制可以对lru的长度和驱逐进行控制.

`cgroup v2`中有一个`v1`中没有的重要主题,就是可以跟踪`page cache`的io回写.  
`v1`无法理解生成`disk IOPS`的group,这样就不能正确的追踪和限制`disk`操作.  
幸运地是,v2版本修复了这些问题,它已经提供了一些新特性可以帮助控制`page cache`回写.

找出所有groups和它们限制的方法是查看`/sys/fs/cgroup`.但是你可以使用更简单的方法:

[`systemd-cgls`和`systemd-top`](https://github.com/facebookincubator/below)


## Memory cgroup files

现在,让我们从`page cache`的角度来回顾`cgroup`中的最重要部分.

+ 1. `memory.current`:展示cgroup和其后代当前使用的总内存,当然包括`page cache`的大小

+ 2. `memory.stat`:显示许多内存计数,最重要的一些信息可以通过`file`来过滤.

+ 3. `memory.numa_stat`:显示每个`NUMA`节点的状态

+ 4. `memory.min`, `memory.low`, `memory.high`, `memory.max`-cgroup限制.  [cgroup v2文档](https://www.kernel.org/doc/html/latest/admin-guide/cgroup-v2.html#usage-guidelines)
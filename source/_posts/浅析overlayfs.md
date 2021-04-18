---
title: 浅析overlayfs
tags: []
id: '2103'
categories:
  - - Linux
  - - linux
    - 文件系统
date: 2020-03-15 12:16:40
---

overlayfs  
overlayfs试图在其它文件系统之上提供一个联合的文件系统视图

Upper and Lower  
overlayfs组合了2个文件系统---Upper文件系统和Lower文件系统。

当同名文件在Upper和Lower中都存在时，Lower中的文件会隐藏，如果是目录，则会合并显示.

Lower文件系统更准确的说法应该是目录树，因为这些目录树可以属于不同的文件系统。

Lower文件系统可以是linux支持的任何文件系统类型,其甚至可以是overlayfs.

目录合并  
对于非目录文件，如果upper和lower中都存在，那么lower中的对象会被隐藏.

如果是目录，upper和lower中的目录会合并.

我们通过下面的命令来指定upperdir, lowerdir,它们将会合并成merged目录.

mount -t overlay overlay -o lowerdir=/root/lowerdir/,upperdir=/root/overlayt/upper,workdir=/root/overlayt/work /root/overlayt/merged

其中lowerdir目录中的内容如下:

\[root@localhost overlayt\]

\# ls -l /root/lowerdir/

总用量 4

\-rw-r--r-- 1 root root 4 3月  15 18:35 1

\-rw-r--r-- 1 root root 0 3月  15 18:35 2

\-rw-r--r-- 1 root root 0 3月  15 18:35 3

drwxr-xr-x 2 root root 6 3月  15 18:35 4

\[root@localhost overlayt\]

#

upper:我们的可写层

lower:底层目录树

merged:联合视图

workdir:需要是一个空目录，且和upper在同一个文件系统中

当在merged目录进行lookup操作时，lookup会在各个目录中搜寻并将结果联合起来缓存到overlayfs文件系统的dentry中。

如果upper,lower中存在相同的目录，则会合并在merged中显示

我们在lower中的dir1目录下创建low1文件

在upper的dir1目录下创建up1文件

在merged中的dir1目录下会同时出现up1,low1.

再来看看删除文件的处理  
whiteouts和opaque目录  
为了支持rm,rmdir操作，且不影响lowdir的内容，overlayfs需要在upper中记录被删除文件的信息。

whiteout是一个主从设备号为0/0的字符设备文件,当在merged目录下有whiteout文件时，lower目录中的同名文件会被忽略.whiteout文件本身也会隐藏，我们在upper目录中能够找到它.

readdir  
当在merged目录调用readdir时，upper和lower目录的文件都会被读取(先读取upper目录,再读取lower目录).这个合并列表会被缓存在file结构中并保持到file关闭。如果目录被两个不同的进程打开并读取，那么它们有不同的缓存。seekdir设置偏移为0后再调用readdir会使缓存无效并进行重建。

这意味着，merged的变化在dir打开期间不会体现，对于很多程序容易忽略这一点。

非目录  
对于非目录对象（文件，符号链接，特殊设备文件）。当对lowdir的对象进行写操作时，会先进行copy\_up操作。创建硬链接也需要copy\_up,软链接则不需要。

copy\_up有时可能不需要，比如以read-write方式打开而实际上并没有进行修改.

copy\_up处理的过程大致如下:

按需创建目录结构  
用相同的元数据创建文件对象  
拷贝文件数据  
拷贝扩展属性  
 

copy\_up完成之后,overlayfs就可以简单的在upper文件系统上提供对对象的访问。

多个lower layers  
我们可以通过":"来指定多个lower layers.

 mount -t overlay overlay -olowerdir=/lower1:/lower2:/lower3 /merged

在上面的例子中，我们没有指定upper,workdir.这意味着overlayfs是只读的。

多个lower layers从右边依次入栈，按照在栈中的顺序在merge中体现。lower3在最底层，lower1在是顶层.

非标准行为  
copy\_up操作创建了一个新的文件。新文件可能处于和老文件不同的文件系统中,因此st\_dev,st\_ino都是新的。

在copy\_up之前老文件上的文件锁不会copy\_up

如果一个有多个硬链接的文件被copy\_up,那么会打断这种链接。改变不会传播给相同硬链接文件。

修改底层underlay文件系统  
线下修改，当overlay没有mount时，允许修改upper,lower目录

不允许在mount overlay时对underlay进行修改。如果对underlay进行了修改，那么overlay的行为是不确定的。尽管不会crash或者deadlock.  
————————————————  
版权声明：本文为CSDN博主「self-motivation」的原创文章，遵循 CC 4.0 BY-SA 版权协议，转载请附上原文出处链接及本声明。  
原文链接：https://blog.csdn.net/happyAnger6/article/details/104885037
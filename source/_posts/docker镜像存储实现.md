---
title: docker镜像存储实现
tags: []
id: '1724'
categories:
  - - cloud
    - Docker
  - - 云计算
date: 2019-08-05 14:11:35
---

为什么要写这篇文章？

网上已经有很多讲docker镜像存储原理的文章了，这篇文章还有必要吗？

网上关于docker镜像的文章大都偏原理，像什么镜像是分层的，是只读的，容器除了镜像的只读层还有自己的可写层等等。这些原理说出来大家好像都懂，但好像什么也没学到。因此我写了这篇偏实现的文章，讲述docker是如何利用联合文件系统存储容器镜像，以及如何建立容器的文件系统。

学习这篇文章之后，你将能够：

*   通过镜像ID找到镜像所有层在磁盘上的存放位置

实战之前先说点基本原理：

docker存储支持多种文件系统。如果没有显示配置，docker按照以下优先级进行选择:

btrfs---->zfs------>overlay2----------->aufs--------->overlay------->devicemapper--------->vfs.

这些文件系统都是一种虚拟文件系统，需要在真实的文件系统之上使用。虚拟指的是不实际管理真正的磁盘。真实的文件系统称之为backing filesystem.

本篇文章以overlay2为基础进行讲解，可以很容易扩展到其它文件系统实现，如aufs.

overlay2能够使用的backing filesystems有以下限制:

*   不支持aufs,zfs,overlay,ecryptfs

*   使用btrfs最好在4.7.0内核之上

docker启动后会有一个rootdir,这个是存储管理的根目录，后面讲解的所有目录都以这个目录为基础（敲黑板^\_^)。docker默认会使用/var/lib/docker作为rootdir.

从centos7开始，centos默认使用了xfs文件系统，因此使用overlay2的backing filesystem为xfs.由于xfs支持磁盘限额，因此docker会开启磁盘限额功能。这样，我们就能够通过overlay2.size选项来限制磁盘限额。

好了，准备工作差不多了，我们开始进入主题。

首先，我们可以通过docker images命令查看当前下载的镜像.

我本地有个kube-apiserver镜像，我们用到为例进行说明。

k8s.gcr.io/kube-apiserver v1.12.0 ab60b017e34f 10 months ago 194MB

可以看到这个镜像的ID为ab60b017e34f(缩写),下面我们来一步步找到这个镜像的所有layer.

通过docker inspect ab60命令查看这个镜像的详细信息。

我们直奔主题，下面的信息对我们有帮助:

"RootFS": {  
"Type": "layers",  
"Layers": \[  
"sha256:f9d9e4e6e2f0689cd752390e14ade48b0ec6f2a488a05af5ab2f9ccaf54c299d",  
"sha256:0721ca6c51792b8eb63ca980193076c474f474aace1fe56271040279c8147ec7"  
\]  
},

这个RootFS信息就是此镜像的所有layers,可以看到这个镜像有2个layer,并且能看到他们的ID。这里有必要先讲述一个几个ID，先知道它们的作用，后面方便继续。

有4个ID需要知道:

*   DiffID:每个layer都有这个ID，是此layer内容的sha256摘要，上面RootFS里看到的ID即为DiffID.

*   ChainID:每个layer也有这个ID，从名字上也能猜出个大概，这是一个链式ID，由父layer递归计算而来。计算公式如下:

![](http://www.anger6.com/wp-content/uploads/2019/08/image-4.png)

公式简单说明一下，如果是第0层layer，则ChainID=DiffID,否则由父layer的ChainID加上一个空格再加上本层的DiffID后做SHA256摘要。

ChainID才能唯一标识一个layer,即使2个layer的DiffID相同，但是由不同的父layerID构建而来则ChainID不会相同。

*   CacheID:一个随机值，产生之后不会改变，标识了layer在底层文件系统存放的目录，后面会更详细介绍
*   parent-id:父layer的ChainID.

由于这4个ID是后面的基础，因此再上2幅图加深理解,图来源于上面例子中的镜像:

![](http://www.anger6.com/wp-content/uploads/2019/08/image-8.png)

![](http://www.anger6.com/wp-content/uploads/2019/08/image-10-1024x191.png)

首先，看下镜像信息在哪里存储。

/var/lib/docker/<fs>:这里的fs为使用的文件系统类型，这里是overlay2.

/var/lib/docker/image/overlay2:

├── distribution  
├── imagedb  
├── layerdb  
└── repositories.json

repositories.json文件存储了当前所有的镜像和其镜像仓库的信息。

imagedb/content/sha256/<IMAGE-ID>目录下是每个镜像的详细信息，文件名即为镜像ID.这里面除了上面提到RootFS信息外，还有镜像的构建历史命令信息。

然后是layerdb/sha256/<ChainID>:这里面的每个目录即为镜像每layer的信息，文件名是layer的ChainID.

可以看到第0层f9d9目录，

drwx------ 2 root root 71 11月 27 2018 f9d9e4e6e2f0689cd752390e14ade48b0ec6f2a488a05af5ab2f9ccaf54c299d/

我们再计算下第1层的ChainID.通过python代码来计算一下:

import hashlib  
s=hashlib.new('sha256')  
s.update(b'sha256:f9d9e4e6e2f0689cd752390e14ade48b0ec6f2a488a05af5ab2f9ccaf54c299d'+b' '+b"sha256:0721ca6c51792b8eb63ca980193076c474f474aace1fe56271040279c8147ec7")  
s.hexdigest()  
'4c737d137c079edec3dd457b1a0a5ab1ec508cfec2bbc1ee141b9d207e5cd5df'

也能够找到第1层layer的ChainID的目录:

drwx------ 2 root root 85 11月 27 2018 4c737d137c079edec3dd457b1a0a5ab1ec508cfec2bbc1ee141b9d207e5cd5df/

我们再看看这些目录下有些什么东西。

f9d9e4e6e2f0689cd752390e14ade48b0ec6f2a488a05af5ab2f9ccaf54c299d/  
├── cache-id  
├── diff  
├── size  
└── tar-split.json.gz

4c737d137c079edec3dd457b1a0a5ab1ec508cfec2bbc1ee141b9d207e5cd5df/  
├── cache-id  
├── diff  
├── parent  
├── size  
└── tar-split.json.gz

可以看到f9d9这个layer有4个文件，而4c73这个layer有5个文件，多了一个parent.

这里的文件就是我们上面介绍的4个ID

*   diff存储的是本layer的DiffID.

*   parent存储的是父layer的ChainID.
*   size是本layer的大小
*   cache-id即本layer在本地文件系统存放的实际目录

好了，通过上面的讲解我们就能够通过镜像ID找到所有layer的信息了，我们再来通过cache-id找下layer实际存储的位置。

/var/lib/docker/overlay2:这个目录下的文件就是对应cache-id的目录。

我们找下4c7e这一layer的目录:

通过cache-id:e0bb81486243dd55fe92dc0830816354507d48f149542e9d1cbf0ea9d4a24ee9

我们找到了对应的目录:

drwx------ 4 root root 55 11月 27 2018 e0bb81486243dd55fe92dc0830816354507d48f149542e9d1cbf0ea9d4a24ee9/

我们再看下这个目录下有什么东西:

.  
├── diff  
│   └── usr  
│   └── local  
│   └── bin  
│   └── kube-apiserver  
├── link  
├── lower  
└── work

可以看到4个目录:

diff:即为本layer的实际内容，可以看到只有一个kube-apiserver的可执行程序

link:这个文件存储的是指向本layer的一个符号链接文件。这个符号链接文件实际存放在/var/lib/docker/overlay2/l目录下。为什么要有这么一个符号链接呢？我们知道overlayfs的原理就是将多个layer挂载到一个目录下，而挂载的多个layer是通过mount options指定的，这个选项的最大长度有限制，通常为4096，如果我们直接使用上面的sha256目录，mount的layer个数就太少了，因此实际mount时使用的是这个符号链接，就能够多支持一些layer了。

lower:本layer的父layer的符号链接

work:overlayfs所使用的work目录

同理，我们可以查看第0层layer的实际内容:

.  
├── diff  
│   ├── bin  
│   ├── dev  
│   ├── etc  
│   ├── home  
│   ├── root  
│   ├── tmp  
│   ├── usr  
│   └── var  
└── link

可以看到实际的内容，因为是第0层，所以少了lower和worker目录.

通过上面的讲解，相信你对docker镜像如何存储的应该有了详细的了解，也能够通过镜像ID找到实际的layer了.
---
title: 让你的项目支持autotools
tags: []
id: '2106'
categories:
  - - Linux
  - - linux
    - linux开发工具
date: 2020-04-16 13:51:44
---

通常我们在linux下用源码安装库或程序，都是使用的autoools工具(虽然可能有些过时，bazel,CMake等等）。但是目前来看，还是一种不错的选择。

典型安装过程:

./configure

make

sudo make install

如果你自己创建了一个开源项目，自己手工编写Makefile的痛苦可想而知，因此还是用autools吧，如果正好你不太好用。就用下面的脚本生成吧。

[https://github.com/happyAnger6/anger6_autotools.git](https://github.com/happyAnger6/anger6_autotools.git)  
————————————————
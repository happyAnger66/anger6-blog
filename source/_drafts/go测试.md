---
title: go测试
tags: []
id: '1860'
categories:
  - - program_language
    - Golang
  - - my_tutorials
    - Golang语言入门
  - - 我的教程
  - - 编程语言
---

能够为自己的程序编写良好的测试是衡量一个优秀程序员的重要指标，不接受任何反驳。

因此，本篇文章讨论如何在go里编写测试。

## go test

go test子命令是Go语言包的测试驱动程序，这些包根据某些约定组织在一起。在一个包目录中，以\_test.go结尾的文件不是go build命令编译的目标，而是go test编译的目标。
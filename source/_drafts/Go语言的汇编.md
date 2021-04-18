---
title: Go语言的汇编
tags: []
id: '1892'
categories:
  - - uncategorized
---

关于Go语言的汇编，需要知道的最重要的一点是：Go汇编器生成的汇编代码不是直接生成特定于底层架构的某种汇编代码（就是说X86上生成的并不是X86汇编代码），你可以认为是Go自己的中间代码。它的风格属于Plan 9风格。

如果你想了解某种架构go汇编的格式，你可以在go的sdk里找到很多例子。比如runtime和math/big.

下面我们来简单的了解一下:

 cat x.go
package main

func main() {
println(3)
}
$ GOOS=linux GOARCH=amd64 go tool compile -S x.go        # or: go build -gcflags -S x.go

--- prog list "main" ---
0000 (x.go:3) TEXT    main+0(SB),$8-0
0001 (x.go:3) FUNCDATA $0,gcargs·0+0(SB)
0002 (x.go:3) FUNCDATA $1,gclocals·0+0(SB)
0003 (x.go:4) MOVQ    $3,(SP)
0004 (x.go:4) PCDATA  $0,$8
0005 (x.go:4) CALL    ,runtime.printint+0(SB)
0006 (x.go:4) PCDATA  $0,$-1
0007 (x.go:4) PCDATA  $0,$0
0008 (x.go:4) CALL    ,runtime.printnl+0(SB)
0009 (x.go:4) PCDATA  $0,$-1
0010 (x.go:5) RET     ,
...

FUNCDATA,PCDATA指令包含了gc需要的信息，是由编译器添加的。
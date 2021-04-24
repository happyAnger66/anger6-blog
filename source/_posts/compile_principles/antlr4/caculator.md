---
title: antlr4实现一个计算器--python语言
tags: [antlr4, 编译原理]
id: '1001'
categories:
  - - 自我修养
    - 编译原理
date: 2021-04-24 14:41:53
---

# 使用antlr4实现一个计算器

## antlr4简介

antlr4是一款强大的语法分析器生成工具,我们可以使用antlr4分析处理各种可以用语法描述的文本(代码,配置文件,报表,json文本),还可以用来实现DSL.  

antlr4能够为我们定义的语法生成AST(一种描述语法与输入文本匹配关系的数据结构),还能够自动生成遍历AST的代码,使我们可以专注于业务逻辑实现.antlr4支持生成各种语言的代码,因此我们可以根据熟悉的语言实现业务功能.

本文章通过实现一个计算器来直观的感受一下antlr4如何使用.

### 定义语法规则

antlr4语法文件以g4为后缀,可以在一个文件中同时定义词法和语法.
+ 词法以全大写表示
+ 语法以全小写表示.
+ 首行使用grammar声明语法名称,需要和文件名完全一致.

```g4
grammar LabeledExpr;

prog: stat+;

stat: expr NEWLINE  # printExpr
    | ID '=' expr NEWLINE # assign
    | NEWLINE   #blank
    ;

expr: expr op=('*' | '/') expr # MulDiv
    | expr op=('+' | '-') expr # AddSub
    | INT   #int
    | ID    #id
    | '(' expr ')'  #parens
    ;

MUL : '*';
DIV : '/';
ADD : '+';
SUB : '-';

ID : [a-zA-Z]+ ;
INT : [0-9]+ ;
NEWLINE : '\r'? '\n';
WS : [ \t]+ -> skip;
```

解释一下:
+ prog: stat+; 定义了一个prog语法,程序是由1个或多个语句构建,antlr4支持类似正则的语法,(+表示一个或多个). '|'表示或的关系.
+ stat: 定义语句的语法,语句可以是表达式,赋值或者换行.
+ expr: 定义表达式的语法,表达式可以是两个表达式的乘除,加减,或者是一个整数,一个变量,或者带括号的表达式. antlr4支持上下文无关方法,可以处理左递归,因此我们可以递归定义语法.
+ "#": #号开关的属于antlr4标签,通过定义标签可以让antlr4为每个备选分支都生成一个遍历方法,否则antlr4只为expr这种语法生成一个遍历方法.
+ MUL,DIV,ADD,SUB: 定义词法的名字,通过定义名字使我们可以在代码中通过这些名字来引用具体的单词.
+ ID,INT,NEWLINE: 定义变量和整型的词法.
+ WS: 定义空白符词法, ->skip为antlr4的指令,表示丢弃当前的单词(每个输入的字符都至少被一条词法规则匹配)

### 生成代码

本文以Python代码举例,antlr4本身使用java语言编写,可以生成不同的目标语言

```shell
antlr4 -no-listener -visitor -Dlanguage=Python3 -o python3 LabeledExpr.g4
```

+ -Dlanguage: 定义输出的目标语言,这里是Python3
+ -o:指定代码输出路径
+ -no-listener -visitor: antlr4默认为处理AST生成监听器代码,这里我们不使用监听器方式而是使用visitor(访问者模式)方式来遍历AST.

通过上述命令antlr4为我们生成以下代码:

1. LabeledExprParser.py: 语法分析器代码,每条语法规则都有对应的处理方法.
2. LabeledExprLexer.py: 词法分析器代码
3. LabeledExpr.tokens: 词法符号对应的数字类型
4. LabeledExprVisitor.py: 语法分析树的访问器,每条语法和标签都有对应的访问方法,通过实现里面的方法实现业务功能.

### 编写处理逻辑

#### 1.首先编写visitor实现处理逻辑

代码比较容易理解,就不再详细展开.定义了一个vars字典用来存储定义的变量,然后在对应的语法里获取相应的数据并计算.

```python
class LabeledExprVisitor(ParseTreeVisitor):
    def __init__(self):
        self.vars = {}

    # Visit a parse tree produced by LabeledExprParser#prog.
    def visitProg(self, ctx:LabeledExprParser.ProgContext):
        return self.visitChildren(ctx)


    # Visit a parse tree produced by LabeledExprParser#printExpr.
    def visitPrintExpr(self, ctx:LabeledExprParser.PrintExprContext):
        value = self.visit(ctx.expr())
        print(value)
        return 0


    # Visit a parse tree produced by LabeledExprParser#assign.
    def visitAssign(self, ctx:LabeledExprParser.AssignContext):
        var = ctx.ID().getText()
        value = self.visit(ctx.expr())
        self.vars[var] = value
        return value


    # Visit a parse tree produced by LabeledExprParser#blank.
    def visitBlank(self, ctx:LabeledExprParser.BlankContext):
        return self.visitChildren(ctx)


    # Visit a parse tree produced by LabeledExprParser#parens.
    def visitParens(self, ctx:LabeledExprParser.ParensContext):
        return self.visit(ctx.expr())


    # Visit a parse tree produced by LabeledExprParser#MulDiv.
    def visitMulDiv(self, ctx:LabeledExprParser.MulDivContext):
        left = self.visit(ctx.expr(0))
        right = self.visit(ctx.expr(0))
        if ctx.op.getType() == LabeledExprParser.MUL:
            return left * right
        return left / right


    # Visit a parse tree produced by LabeledExprParser#AddSub.
    def visitAddSub(self, ctx:LabeledExprParser.AddSubContext):
        left = self.visit(ctx.expr(0))
        right = self.visit(ctx.expr(1))
        if ctx.op.type == LabeledExprParser.ADD:
            return left + right
        return left - right


    # Visit a parse tree produced by LabeledExprParser#id.
    def visitId(self, ctx:LabeledExprParser.IdContext):
        var = ctx.ID().getText()
        if var in self.vars: # use before assignment?
            return self.vars[var]
        return 0


    # Visit a parse tree produced by LabeledExprParser#int.
    def visitInt(self, ctx:LabeledExprParser.IntContext):
        return int(ctx.INT().getText())
```

#### 2.编写主程序,将输入与语法分析结合

```python
caculator.py:

import sys
from antlr4 import *
from LabeledExprLexer import LabeledExprLexer
from LabeledExprParser import LabeledExprParser
from LabeledExprVisitor import LabeledExprVisitor

def main(argv):
    input_stream = FileStream(argv[1])
    lexer = LabeledExprLexer(input_stream) # 将输入文件交给记法分析器
    tokens = CommonTokenStream(lexer)  # 将记法分析器传递给token生成一个个单词
    parser = LabeledExprParser(tokens) # 将单词传递给语法分析器
    tree = parser.prog() # 调用prog语法生成AST

    eval = LabeledExprVisitor() # 调用visitor遍历生成的AST
    eval.visit(tree)

if __name__ == '__main__':
    main(sys.argv)
```

#### 3.验证程序

输入文本:
```txt
1.text

a=5
b=4
a+b
```

```shell
python3 caculator.py 1.text

输出:9
```

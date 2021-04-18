---
title: zip暴力破解工具Python实现
tags: []
id: '2110'
categories:
  - - Python
date: 2020-05-04 12:27:15
---

## 原理：

１.指定密码包括的字符种类，如：数字，小写字母，大写字母，特殊字符

２.指定密码的长度

３.遍历所有可能的组合暴力破解

在密码比较简单的时候比较有用。

## 使用指导:

optional arguments:  
  -h, --help           show this help message and exit  
  -a                   指定密码中包含小写字母.  
  -A                  指定密码中包含大写字母..  
  -n                  指定密码中包含数字.  
  -N N              指定密码的长度.  
  --spec SPEC         单独指定密码中包含的字符，字符串形式,指定密码中包含'.'和'\*',则指定".\*".  
  --filepath FILEPATH  待破解的zip加密文件路径.

## 使用举例：

*   指定密码由数字构成，密码长度为３位

python main.py -n -N 3 D:/xxx.zip

*   指定密码由大小写字母构成，密码长度为１０位

python main.py -a -A -N 10 D:/xxx.zip

*   指定密码由\['!', '@',  '$', '&'\]４个特殊字符构成，密码长度为６位

python main.py -N 10 --spec "!@$&" D:/xxx.zip

## 代码

### github地址:

[https://github.com/happyAnger6/angerZipDecode](https://github.com/happyAnger6/angerZipDecode)

### python代码:

angerZipDecode\\args.py:

```
import argparse

def setup_args():
    parser = argparse.ArgumentParser(description='anger zip brute force decode.')

    parser.add_argument('-a', action='store_true', help='add lower case(a-z) letter to password.')
    parser.add_argument('-A', action='store_true', help='add upper case(A-Z) letter to password..')
    parser.add_argument('-n', action='store_true', help='add numeric(0-9) to password..')
    parser.add_argument('-N', action='store', required=True, help='total word nums of the password. ')
    parser.add_argument('--spec', action='store', help='add special words to password..')
    parser.add_argument('--filepath', action='store', required=True, help='zip file path. ')

    return parser.parse_args()
```

![](http://www.anger6.com/wp-content/uploads/2020/05/image-1.gif)

angerZipDecode\\main.py:

```
import os
import sys
from zipfile import ZipFile

def gen_passwd_elems(args):
    total_elems = []
    if args.a: #use lower letter
        lower_word  = [chr(ord('a') + i) for i in range(26)]
        total_elems.extend(lower_word)

    if args.A: #use upper letter
        upper_word = [chr(ord('A') + i) for i in range(26)]
        total_elems.extend(upper_word)

    if args.spec: #use user spec letter
        total_elems.extend(list(set(args.spec)))

    if args.n: #user numeric
        digits = [i for i in range(10)]
        total_elems.extend(digits)

    return total_elems

def gen_passwd_iter(elements, pwnums=6, curnum=1):
    for i in elements:
        if pwnums == curnum:
            yield str(i)
        else:
            for j in gen_passwd_iter(elements, pwnums, curnum+1):
                yield str(i) + str(j)

if __name__ == "__main__":
    from args import setup_args

    args = setup_args()
    filename = args.filepath
    passwd_len = int(args.N)
    passwd_words = gen_passwd_elems(args)

    zfile = ZipFile(os.path.abspath(filename))
    for l in range(1, passwd_len+1):
        for pwd in gen_passwd_iter(passwd_words, l, 1):
            try:
                zfile.extractall(pwd=pwd.encode('utf-8'))
                print('Try password:{0} successed.'.format(pwd))
                sys.exit(0)
            except Exception as e:
                print('Try password:{1} failed! Error:{0}'.format(e, pwd))
```

![](http://www.anger6.com/wp-content/uploads/2020/05/image.gif)
---
title: apollo平台dreamview前端代码调试方法
tags: []
id: '2022011301'
categories:
  - 自动驾驶
  - hmi
date: 2022-01-13 21:30:52
---

# 简介

apollo是百度开源的自动驾驶平台,本系列文章主要讲解一下平台学习中的经验和方法.

## dreamview

dreamview是apollo平台的hmi界面,关于dreamview的介绍可以参考[github](https://github.com/ApolloAuto/apollo/tree/master/modules/dreamview). dreamview的代码分为frontend前端代码和backend后端代码两部分,这篇文章主要讲述frontend代码的调试方法

### 调试dreamview frontend代码

github上dreamview的开源代码是已经构建好的dist包,因此不方便使用chrome跟踪调试,apollo构建框架bazel默认也不会构建dreamview的fronted代码.
因此,需要我们手工编译调试版本.

#### 具体步骤

##### 1. 首先按照github上的步骤将apollo代码下载好并进行一次全量构建

[具体步骤](https://github.com/ApolloAuto/apollo/blob/master/docs/quickstart/apollo_software_installation_guide.md)

##### 2. 单独编译dreamview代码

`进入代码目录`

```shell
cd apollo/modules/dreamview/frontend

```

`修改webpack配置文件,打开source-map功能`
```shell
vim webpack.config.js:

 devtool: "cheap-source-map"   # 将"cheap-source-map"修改为"eval-source-map"
```

`重新构建调试版本`
```shell
npm run-script build
```

##### 3. 启动apollo,就可以用chrome调试了
```shell
bash apollo/scripts/bootstrap.sh start
```
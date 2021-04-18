---
title: gperftools使用
tags: []
id: '1869'
categories:
  - - system_arch
    - 性能分析
  - - 系统架构
---

_gperftools是Google使用的性能分析工具。_

使用它只需要3步:

*   将其动态库链接到目标程序

*   运行代码

*   分析输出

### 链接库程序

要将cpu profiler安装到你的可执行程序里，需要在链接时使用-lprofiler.(也可以使用LD\_PRELOAD在运行时添加profiler. 比如%env LD\_PRELOAD="/usr/lib/libprofiler.so" <binary>,但是不是建议使用这种方法)

这并没有打开CPU profiling,只是插入了代码。所以在实践中，在开发环境中总是链接-lprofiler,我们在Google也是这么做的.(但是，因为任何人都可能通过设置环境变量来打开profiler,因此，不建议在生产环境里连接profiler到二进制程序里)

### 运行代码

有几种方法可以打开cpu profiling.

*   通过CPUPROFILE环境变量定义dump profile的输出文件.比如你有一个链接了-lprofiler的ls程序.

% env CPUPROFILE=ls.prof /bin/ls

*   除了定义CPUPROFILE环境变量，你还可以定义CPUPROFILESIGNAL.通过它定义控制CPUPROFILE的信号。定义的信号需要是没有被应用程序使用的信号。通过信号控制cpu profile的开关，默认处于关闭状态。比如，你有一个链接了libprofiler的chrome,可以通过下面的方式运行:

% env CPUPROFILE=chrome.prof CPUPROFILESIGNAL=12 /bin/chrome &

然后你可以通过发送信号触发profiling.

% killall -12 chrome

过一段时间，再通过发送信号停止profiling并记录文件

% killall -12 chrome

*   你还可以在代码里，对称的使用`ProfilerStart()`和`ProfilerStop()`. (这些函数声明在`<gperftools/profiler.h>`中) `ProfilerStart()`使用profile-filename作为参数.

在Linux2.6及以上内核，profiling在多线程环境里能够正常工作，能够自动的对所有线程进行profiling.

在Linux2.4里，profiling只能profile主线程（由于内核在itimers和线程实现中的bug). Profiling对于子进程能够正常工作:每个子进程获取自己名字相关的profile(由CPUPROFILE和子进程的pid组合而成).

出于安全的考虑，CPU profiling将不会输出到文件，因此对于setuid程序不可用.

想要了解更多相关高级函数的用法,可以查看 gperftools/profiler.h 文件。

比如其中的ProfilerFlush()和ProfilerStartWithOptions()

### 修改运行时的行为

有以下环境变量可以控制cpu profiler运行时的行为:

`CPUPROFILE_FREQUENCY=_x_`

默认值 : 100

每秒的采样次数.

CPUPROFILE\_REALTIME 默认不设置 如果设置了值（包含0和空串），则采集时使用ITIMER\_REAL代替ITIMER\_PROF.通常ITIMER\_REAL没有ITIMER\_PROF精度高，而且与alarm的配合也不好。所以没有充足的理由，最好使用ITIMER\_PROF

### 分析输出
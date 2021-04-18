---
title: 怎么使用context
tags: []
id: '1903'
categories:
  - - program_language
    - Golang
  - - golang
    - go标准库
date: 2019-08-18 13:55:57
---

## 简介

context库是go 1.7中加入的，本篇文章主要是讲解如何正确的使用它。

### 缘起

一切有为法，让我们先来看看golang里加入context的缘由。

在一个go实现的服务器程序中，通常我们对每个请求使用一个goroutine来进行处理。在请求处理里，我们还有可能启动新的goroutine来访问一些后台程序，如进行数据库操作或发起RPC请求。这些处理这个请求相关的goroutines,经常需要访问与当前请求相关的信息，如用户ID，认证token,请求的截止时间等等。当请求被取消或者超时的时候，所有处理这个请求的goroutines都应该尽快退出，这样才能够对相关的资源进行快速地回收。

因此，在Google内部，开发了一个context包，用于把请求相关的值，取消信号，API截止日期等信息在所有处理这个请求相关的goroutines里进行方便地传递。这个包最终也发布到了go 1.7里.

## 如露亦如电

我们再来详细地了解一下context的接口。

context包的核心就是context接口.下面是从源码包里摘录的其核心部分。

// A Context carries a deadline, cancelation signal, and request-scoped values
// across API boundaries. Its methods are safe for simultaneous use by multiple
// goroutines.
type Context interface {
    // Done returns a channel that is closed when this Context is canceled
    // or times out.
    Done() <-chan struct{}

    // Err indicates why this context was canceled, after the Done channel
    // is closed.
    Err() error

    // Deadline returns the time when this Context will be canceled, if any.
    Deadline() (deadline time.Time, ok bool)

    // Value returns the value associated with key or nil if none.
    Value(key interface{}) interface{}
}

*   Done:这个方法返回一个只读的通道,这个通道可以被运行在当前context上的函数当作一个取消信号使用:当这个通道被关闭后，正在处理的函数应该终止操作并返回。

*   Err:Err方法返回一个错误信息用来说明context被取消的原因。

细心的你可能发现了Context没有Cancel方法，这是为什么呢？这和Done方法只返回了一个只读通道的原因是一样的:接收到取消信号的函数通常不会是发送取消信号的函数（isn't it?).尤其是当父操作启动goroutines来处理子操作时，这些子操作不应该能够取消父操作.

*   `WithCancel`函数提供了取消一个新创建的Context的方法.

*   Context对于并发的多个goroutines是安全的。在代码里，我们可以将一个context传递给任意数量的goroutines然后取消Context并通知所有的gourintes.

*   Deadline:方法允许函数来判断它们是否应该启动;如果可用的时间不多了，可能就没必要干活了。在代码里，我们还可以利用deadline来对I/O操作设置超时。

*   `Value方法允许Context设置请求范围内的值。这个数据应该能够被并发的多个goroutines安全地访问.`

### 从Context继承

context包提供用来从已有的context继承产生出新的Context的函数。这些值构成了一棵树:当一个Context取消时，所有从其继承衍生出来的Contextx也被取消.

*   Background是Context树的根，它从来不会被取消.

下面是源码包里的描述:

// Background returns an empty Context. It is never canceled, has no deadline,
// and has no values. Background is typically used in main, init, and tests,
// and as the top-level Context for incoming requests.
func Background() Context

*   `WithCancel`和`WithTimeout`返回衍生出的Context值,可以在父Context之前被取消.当请求处理函数返回时，与这个请求相关的Context就被取消了.WithCancel对于需要取消使用多个副本处理冗余请求的场景也十分有用.WithTimeout用于为访问后端服务的请求设置deadline.

下面是源码包里的相关描述:

// WithCancel returns a copy of parent whose Done channel is closed as soon as
// parent.Done is closed or cancel is called.
func WithCancel(parent Context) (ctx Context, cancel CancelFunc)

// A CancelFunc cancels a Context.
type CancelFunc func()

// WithTimeout returns a copy of parent whose Done channel is closed as soon as
// parent.Done is closed, cancel is called, or timeout elapses. The new
// Context's Deadline is the sooner of now+timeout and the parent's deadline, if
// any. If the timer is still running, the cancel function releases its
// resources.
func WithTimeout(parent Context, timeout time.Duration) (Context, CancelFunc)

`WithValue`用于将一个请求范围相关的值设置到Context.

下面是源码包里的描述:

// WithValue returns a copy of parent whose Value method returns val for key.
func WithValue(parent Context, key interface{}, val interface{}) Context

## 实战

说了这么多道理，还是来个实际的例子理解起来容易。

我们实现一个HTTP服务程序，这个程序处理  `/search?q=golang&timeout=1s` 这个的URL。这个URL查询"golang"关键字的相关信息，超时时间是1s.我们将这个请求转给Google查询API处理，并对查询结果进行一些渲染工作。

我们的代码由以下3部分组成:

*   [server](https://blog.golang.org/context/server/server.go)提供了`main函数实现并处理/search URL.`
*   [userip](https://blog.golang.org/context/userip/userip.go) 提供了解析用户IP并将其关联到Context的功能.
*   [google](https://blog.golang.org/context/google/google.go) 提供了Search函数用于将请求发送给Google处理.

### server实现

server程序处理像`/search?q=golang`这样的url请求.我们注册handleSearch函数用于处理/search这个url.这个handler会创建一个初始的Context的名为ctx,并会在处理结束时调用cancel.如果请求URL里有timeout参数，Context会在超时时自动取消:.

下面是具体的代码：

func handleSearch(w http.ResponseWriter, req \*http.Request) {
    // ctx是这个handler使用的Context.调用cancel来关闭ctx.Done channel,这个handler发起的所有操作都会收到取消信号.
    var (
        ctx    context.Context
        cancel context.CancelFunc
    )
    timeout, err := time.ParseDuration(req.FormValue("timeout"))
    if err == nil {
        // 请求里有timeout,因此创建一个有超时时间的context.
        ctx, cancel = context.WithTimeout(context.Background(), timeout)
    } else {
        ctx, cancel = context.WithCancel(context.Background())
    }
    defer cancel() // 函数返回里调用cancel().

handler会使用userip包里提供的函数从请求里取出用户IP并设置到context里，后面的请求会使用这个ip.

// 检查是否携带查询参数.
    query := req.FormValue("q")
    if query == "" {
        http.Error(w, "no query", http.StatusBadRequest)
        return
    }

    // 使用userip包里的函数取出userIP,并存储到ctx里.
    userIP, err := userip.FromRequest(req)
    if err != nil {
        http.Error(w, err.Error(), http.StatusBadRequest)
        return
    }
    ctx = userip.NewContext(ctx, userIP)

然后调用Google api进行查询.

// 调用google api进行查询.
    start := time.Now()
    results, err := google.Search(ctx, query)
    elapsed := time.Since(start)

查询成功,处理结果.

 if err := resultsTemplate.Execute(w, struct {
        Results          google.Results
        Timeout, Elapsed time.Duration
    }{
        Results: results,
        Timeout: timeout,
        Elapsed: elapsed,
    }); err != nil {
        log.Print(err)
        return
    }

### userip实现

userip包提供了解析用户IP的功能.Context提供了一个key-value map.key和value都是interface{}类型.key必须支持相等判断,values必须能够安全地并多个goroutines并发访问.userip包隐藏了这个map的细节,并提供了对Context值的强类型访问.

为了避免冲突,userip定义了未导出的key类型.

// key类型没有导出,为了防止和其它包里定义的context keys冲突.
type key int

// userIPkey是用户IP的key,它的值是0.如果要定义其它的context keys,需要使用不同的值.
const userIPKey key = 0

*   `FromRequest`从http.Request里解析userIP.

func FromRequest(req \*http.Request) (net.IP, error) {
    ip, \_, err := net.SplitHostPort(req.RemoteAddr)
    if err != nil {
        return nil, fmt.Errorf("userip: %q is not IP:port", req.RemoteAddr)
    }

*   `NewContext`返回一个设置了userIP的新Context:

func NewContext(ctx context.Context, userIP net.IP) context.Context {
    return context.WithValue(ctx, userIPKey, userIP)
}

*   `FromContext`从Context里获取userIP:

func FromContext(ctx context.Context) (net.IP, bool) {
    // ctx.Value对于不存在的key返回nil.
net.IP断言对于nil返回的ok=false.
    userIP, ok := ctx.Value(userIPKey).(net.IP)
    return userIP, ok
}

### google包

gooe.Search函数创建一个使用Google Web Search API的HTTP请求,并解析返回的JSON结果.它接受一个Context参数并在ctx.Done关闭时直接返回。

Google Web Search API需要使用user IP作为查询参数.

func Search(ctx context.Context, query string) (Results, error) {
    // 准备Google API请求.
    req, err := http.NewRequest("GET", "https://ajax.googleapis.com/ajax/services/search/web?v=1.0", nil)
    if err != nil {
        return nil, err
    }
    q := req.URL.Query()
    q.Set("q", query)

    // 从ctx里解析user IP并使用.
    if userIP, ok := userip.FromContext(ctx); ok {
        q.Set("userip", userIP.String())
    }
    req.URL.RawQuery = q.Encode()

*   Search使用了httpDo这个帮助函数用于提交http请求,并在ctx.Done时取消正在进行的处理.Search向httpDo传递了一个闭包函数用于处理HTTP响应:

 var results Results
    err = httpDo(ctx, req, func(resp \*http.Response, err error) error {
        if err != nil {
            return err
        }
        defer resp.Body.Close()

        // Parse the JSON search result.
        // https://developers.google.com/web-search/docs/#fonje
        var data struct {
            ResponseData struct {
                Results \[\]struct {
                    TitleNoFormatting string
                    URL               string
                }
            }
        }
        if err := json.NewDecoder(resp.Body).Decode(&data); err != nil {
            return err
        }
        for \_, res := range data.ResponseData.Results {
            results = append(results, Result{Title: res.TitleNoFormatting, URL: res.URL})
        }
        return nil
    })
    
    return results, err

*   `httpDo`函数在一个新的goroutine里发起HTTP请求并处理响应.如果在goroutine退出之前ctx.Done则取消请求。

func httpDo(ctx context.Context, req \*http.Request, f func(\*http.Response, error) error) error {
    // 在一个goroutine里处理HTTP请求并将响应交给f处理.
    c := make(chan error, 1)
    req = req.WithContext(ctx)
    go func() { c <- f(http.DefaultClient.Do(req)) }()
    select {
    case <-ctx.Done():
        <-c // 等待f返回.
        return ctx.Err()
    case err := <-c:
        return err
    }
}

希望通过上面的例子，你能够理解并正确地使用Context包.

## 总结

在Google内部,要求Go程序员将Context作为请求和响应路径上所有函数的第一个参数.这样不同小组的程序员能够良好的合作在一起.这提供了一种简单的控制超时和取消的机制,并能够保证证书这种关键信息能够在程序里正确地传递.

希望使用Context这种机制的服务框架应该提供Context的实现，

用于在它们的包和那些使用Context参数的包之间建立一座桥梁.它们提供的库能够从调用代码里接受Context,这样就能够在请求相关的数据和取消上建立一种通用的接口。Context使包开发者能够更容易的共享代码来创建可伸缩的服务.
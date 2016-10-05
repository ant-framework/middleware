Ant框架使用的中间件
=======================================

## 中间件运行流程

```
(begin) ----------------> function() -----------------> (end)
            ^   ^   ^                   ^   ^   ^
            |   |   |                   |   |   |
            |   |   +------- Mn() ------+   |   |
            |   +----------- ...  ----------+   |
            +--------------- M1() --------------+
```

简单使用
-----

```php
<?php
use Ant\Middleware\Middleware;
use Ant\Middleware\Arguments;

require 'vendor/autoload.php';

$handle = [
    function($str1){
        echo 123;
        yield;
        echo 321;
    },
    function($str1){
        echo 456;
        yield new Arguments($str1,'world');
        echo 654;
    }
];

(new Middleware)->send('hello')->through($handle)->then(function($str1,$str2){
    echo "($str1 $str2)";
});

// output "123456(hello world)654321"
```

### 通过`yield`关键词获取重新入栈的信息

```php
<?php
use Ant\Middleware\Middleware;
use Ant\Middleware\Arguments;

require 'vendor/autoload.php';

$handle = [
    function(){
        $returnInfo = (yield new Arguments('hello'));
        echo $returnInfo;
    }
];

(new Middleware)->send('hello')->through($handle)->then(function($hello){
    return $hello.' world';
});

// output "hello world"
```

### 打断中间件调用链
打断调用链有两种方式，一种是`yield false`，一种是抛出异常

* `yield false` 这种方式会打断中间件的调用，但是依旧会执行then中的闭包函数
```php
use Ant\Middleware\Middleware;
use Ant\Middleware\Arguments;

require 'vendor/autoload.php';

$handle = [
    function(){
        echo 123;
        yield false;
        echo 321;
    },
    function(){
        echo 456;
        yield;
        echo 654;
    },
];

(new Middleware)->send()->through($handle)->then(function(){
    echo 'hello world';
});

//output "123hello world321"
```

* 在中间件的运行过程中，如果出现异常，中间件的往下的调用链会被打断，然后开始回调中间件，回调的过程是以责任链的方式完成，如果`内层`中间件无法处理异常，那么`外层`中间件会尝试捕获这个异常，如果一直无法处理,异常将会抛到最顶层来处理，如果处理了这个异常，那么异常回调链将会被打断，程序会返回至中间件启动的位置（内层外层可以参考流程图）

```php
use Ant\Middleware\Middleware;
use Ant\Middleware\Arguments;

require 'vendor/autoload.php';

$handle = [
    function(){
        try{
            yield;
        }catch(Exception $e){
            echo "Catch an exception in method 1 , which is thrown by the ".$e->getMessage();
        }
    },
    function(){
        try{
            yield;
        }catch(RuntimeException $e){
            // 此处无法处理方法3抛出的异常，所以跳过了
            echo "Catch an exception in method 2 , which is thrown by the ".$e->getMessage();
        }
    },
    function(){
        throw new Exception('method 3');
    },
];

(new Middleware)->send()->through($handle)->then(function(){
    echo 'hello world';
});

//output "Catch an exception in method 1 , which is thrown by the method 3"
```

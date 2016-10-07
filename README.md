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

简单的介绍
-----
> 1. 因为是基于PHP的协同进行的开发，并且运用了一些新特性，要求使用版本为`>=5.6.0`
> 2. 每次`yield`后，会挂起当前中间件，执行下一个中间件
> 3. `yield`不是必须的，如果没有`yield`，此中间件被执行完毕之后不会被再次回调
> 4. 在执行完`function`之后会尝试恢复之前挂起的中间件，恢复的时候可以进行传值
> 5. 如果出现了异常，中间件的调用链会停止，并且恢复之前挂起的中间件，尝试使用这些恢复的中间件处理异常
> 6. 参考 : http://php.net/manual/zh/language.generators.syntax.php


### 使用

```php
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
        yield new Arguments($str1,'world'); //改变在中间件中传递的变量
        echo 654;
    }
];

(new Middleware)				    //实例中间件
	->send('hello')                 //要通过中间件变量
	->through($handle)              //使用的中间件,必须为可回调类型(callable类型)
	->then(function($str1,$str2){   //挂起所有中间件之后回调的函数
	    echo "($str1 $str2)";
	});

// output "123456(hello world)654321"
```

### 通过`yield`关键词获取重新入栈的信息

> `then`中闭包函数返回的值，会在恢复中间件的时候传递给每个协同函数，协同函数可以通过`$message = yield`这样的语法来获取值(更详细的语法可以去参考PHP手册)，根据不同的版本有两种方式来改变重新入栈时传递的值，PHP7以上通过`return`就可以改变传递的值，5.6使用第二个`yield`返回，注意是`第二个`

* PHP7改变传递的值

```php
/////////////////// PHP7 ///////////////////
use Ant\Middleware\Middleware;
use Ant\Middleware\Arguments;

require 'vendor/autoload.php';

$handle = [
    function(){
        $returnInfo = yield;
        echo $returnInfo;
    },
    function(){
        $returnInfo = yield;
        return $returnInfo.' world';  //使用return改变传递参数
    }
];

(new Middleware)->send('hello')->through($handle)->then(function($hello){
    return $hello;
});
// output "hello world"
```

* PHP5.6改变传递的值

```
/////////////////// PHP5.6 ///////////////////
use Ant\Middleware\Middleware;
use Ant\Middleware\Arguments;

require 'vendor/autoload.php';

$handle = [
    function(){
        $returnInfo = yield;
        echo $returnInfo;
    },
    function(){
        $returnInfo = yield;
        yield $returnInfo.' world';  // 使用yield传递传递参数
    }
];

(new Middleware)->send('hello')->through($handle)->then(function(){
    return $hello;
});
```

### 打断中间件调用链

* 打断调用链有两种方式，一种是`yield false`，一种是抛出异常

> `yield false` 这种方式会打断中间件的调用，但是依旧会执行then中的闭包函数

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

> 在中间件的运行过程中，如果出现异常，中间件的往下的调用链会被打断，然后开始回调中间件，回调的过程是以责任链的方式完成，如果`内层`中间件无法处理异常，那么`外层`中间件会尝试捕获这个异常，如果一直无法处理,异常将会抛到最顶层来处理，如果处理了这个异常，那么异常回调链将会被打断，程序会返回至中间件启动的位置（内层外层可以参考流程图）

 * 注意：在中间件中捕获时要注意，异常是在`yield`处抛出，如果有必须执行并且与异常无关的代码请放在`yield`前执行，因为异常的抛出可能会让这些代码无法执行

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

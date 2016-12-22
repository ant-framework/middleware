<?php
namespace Ant\Middleware;

use Ant\Middleware\Pipeline;
use Ant\Middleware\Arguments;

class PipelineTest extends \PHPUnit_Framework_TestCase
{
    protected $isPHP7;

    public function setUp()
    {
        $this->isPHP7 = version_compare(PHP_VERSION, '7.0.0', '>=');
    }

    /**
     * 测试参数的传递
     */
    public function testParameterTransfer()
    {
        ob_start();
        $nodes = [
            function(){
                echo 123;
                yield new Arguments('hello');
                echo 321;
            },
            function($str1){
                echo 456;
                yield new Arguments($str1,'world');
                echo 654;
            }
        ];

        (new Pipeline)->send()->through($nodes)->then(function($str1,$str2){
            echo "($str1 $str2)";
        });

        $output = ob_get_clean();

        $this->assertEquals('123456(hello world)654321',$output);

        //================================================//

        $nodes = [
            function($str){
                $this->assertEquals("foobar",$str);
                yield new Arguments('hello');
            },
            function($str){
                $this->assertEquals('hello',$str);
                yield new Arguments($str,'world');
            }
        ];

        (new Pipeline)->send('foobar')->through($nodes)->then(function($str1,$str2){
            $this->assertEquals('hello world',"{$str1} {$str2}");
        });
    }

    /**
     * 测试中间件对输出参数的影响
     */
    public function testEffectOfNodesOnTheReturnResult()
    {
        if($this->isPHP7){
            $pipeline = (new Pipeline)->push(function(){
                $returnInfo = yield;
                return $returnInfo.'bar';  //使用return改变传递参数
            });
        }else{
            $pipeline = (new Pipeline)->push(function(){
                $returnInfo = yield;
                yield $returnInfo.'bar';  //PHP5.6协同不支持获取Return value
            });
        }

        $result = $pipeline->send('foo')->then(function($foo){
            return $foo;
        });

        $this->assertEquals("foobar",$result);
    }

    /**
     * 测试每个管道节点对整体流程影响
     */
    public function testInfluenceOfPipeNodeOnTheWholeProcess()
    {
        ob_start();
        $nodes = [
            function(){
                echo 123;
                yield;
                echo 321;
            },
            function(){
                echo 456;
                yield false; //打断之后所有回调,直接在此结束
                echo 654;
            },
        ];

        (new Pipeline)->send()->through($nodes)->then(function(){
            echo 'foobar';
        });

        $output = ob_get_clean();
        $this->assertEquals('123456',$output);

        //================================================//

        $nodes = [
            function(){
                try{
                    yield;
                }catch(\Exception $e){
                    echo "foo".$e->getMessage();
                }
            },
            function(){
                try{
                    yield;
                }catch(\RuntimeException $e){
                    echo "fii".$e->getMessage();
                }
            }
        ];

        ob_start();
        (new Pipeline)->send()->through($nodes)->then(function(){
            throw new \Exception('bar');
        });

        $output = ob_get_clean();
        $this->assertEquals('foobar',$output);
    }

    public function testIllegalMiddleware()
    {
        $nodes = ['string',['foo'=>'bar']];

        try{
            (new Pipeline)->send()->through($nodes)->then(function(){
                return "hello world";
            });
        }catch (\Exception $e){
            $this->assertInstanceOf(\InvalidArgumentException::class,$e);
        }
    }
}
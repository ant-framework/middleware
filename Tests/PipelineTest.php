<?php
namespace Ant\Middleware;

use Ant\Middleware\Pipeline;

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

        (new Pipeline())->pipe(null, [
            function ($ctx) {
                echo 123;
                $ctx->str1 = "hello";
                yield;
                echo 321;
            },
            function ($ctx) {
                echo 456;
                $ctx->str2 = "world";
                yield;
                echo 654;
            },
            function ($ctx) {
                echo "({$ctx->str1} {$ctx->str2})";
            }
        ]);

        $output = ob_get_clean();

        $this->assertEquals('123456(hello world)654321',$output);

        //================================================//

        (new Pipeline())->pipe(null, [
            function ($ctx) {
                $this->assertEquals("bar", $ctx->foo);
                $ctx->str1 = 'hello';
                yield;
            },
            function ($ctx) {
                $this->assertEquals('hello', $ctx->str1);
                $ctx->str2 = 'world';
                yield;
            },
            function ($ctx) {
                $this->assertEquals('hello world',"{$ctx->str1} {$ctx->str2}");
            }
        ], new Context(['foo' => 'bar']));
    }

    /**
     * 测试中间件对输出参数的影响
     */
    public function testEffectOfNodesOnTheReturnResult()
    {
        $pipeline = new Pipeline();

        if ($this->isPHP7) {
            $pipeline->insert(function () {
                $returnInfo = yield;
                return $returnInfo.'bar';  //使用return改变传递参数
            });
        } else {
            $pipeline->insert(function () {
                $returnInfo = yield;
                yield $returnInfo.'bar';  //PHP5.6协同不支持获取Return value
            });
        }

        $result = $pipeline->pipe(null, [
            function () {
                return 'foo';
            }
        ]);

        $this->assertEquals("foobar",$result);
    }

    /**
     * 测试每个管道节点对整体流程影响
     */
    public function testInfluenceOfPipeNodeOnTheWholeProcess()
    {
        ob_start();

        (new Pipeline())->pipe(null, [
            function () {
                echo 123;
                yield;
            },
            function () {
                // 终止后续中间件
                yield false;
                echo 456;
            },
            function () {
                echo 'foobar';
            }
        ]);

        $output = ob_get_clean();
        $this->assertEquals('123', $output);

        //================================================//

        ob_start();

        (new Pipeline)->pipe(null, [
            function () {
                try {
                    yield;
                } catch (\Exception $e) {
                    echo "foo".$e->getMessage();
                }
            },
            function () {
                try {
                    yield;
                } catch (\RuntimeException $e){
                    echo "fii".$e->getMessage();
                }
            },
            function () {
                throw new \Exception('bar');
            }
        ]);

        $output = ob_get_clean();
        $this->assertEquals('foobar',$output);
    }

    public function testIllegalMiddleware()
    {
        $nodes = ['string', ['foo'=>'bar']];

        try {
            (new Pipeline)->insert($nodes)->pipe([
                function () {
                    return "hello world";
                }
            ]);
        } catch (\Exception $e) {
            $this->assertInstanceOf(\InvalidArgumentException::class,$e);
        }
    }
}
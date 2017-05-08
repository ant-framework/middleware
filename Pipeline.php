<?php
namespace Ant\Middleware;

use Closure;
use Generator;
use Exception;
use InvalidArgumentException;

/**
 * Todo Server版
 * Todo 中间件处理完异常后,继续回调剩下来的栈
 *
 * Class Pipeline
 * @package Ant\Middleware
 */
class Pipeline
{
    /**
     * 默认加载的中间件
     *
     * @var array
     */
    protected $nodes = [];

    /**
     * 7.0之前使用第二次协同返回数据,7.0之后通过getReturn返回数据
     *
     * @var bool
     */
    protected $isPhp7 = false;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var \SplStack
     */
    protected $stack;

    /**
     * Middleware constructor.
     */
    public function __construct()
    {
        $this->stack = new \SplStack();
        $this->isPhp7 = version_compare(PHP_VERSION, '7.0.0', '>=');
    }

    /**
     * 设置经过的中间件
     *
     * @param array|callable $nodes 经过的每个节点
     * @return $this
     */
    public function insert($nodes)
    {
        $nodes = is_array($nodes) ? $nodes : func_get_args();

        foreach ($nodes as $node) {
            if (!is_callable($node)) {
                throw new InvalidArgumentException('Pipeline must be a callback');
            }
        }

        $this->nodes = array_merge($this->nodes, $nodes);

        return $this;
    }

    /**
     * @param array $nodes
     * @param ContextInterface $context
     * @return mixed|null
     */
    public function pipe(array $nodes = [], ContextInterface $context = null)
    {
        // 初始化参数
        $result = null;
        $this->insert($nodes);
        $this->context = $context ?: new Context();

        try {
            foreach ($this->nodes as $node) {
                $generator = call_user_func($node, $this->context);

                if (!$generator instanceof Generator) {
                    $result = $generator;
                } else {
                    // 将协同函数添加到函数栈
                    $this->stack->push($generator);
                    $yieldValue = $generator->current();

                    // Todo 通过信号进行判断,暂停,跳过,更改参数
                    if ($yieldValue === false) {
                        // 打断中间件执行流程
                        return false;
                    }
                }
            }

            // 回调函数栈
            while (!$this->stack->isEmpty()) {
                $generator = $this->stack->pop();
                $generator->send($result);
                // 尝试用协同返回数据进行替换,如果无返回则继续使用之前结果
                $result = $this->getResult($generator);
            }
        } catch (Exception $exception) {
            $result = $this->tryCatch($exception);
        }

        return $result;
    }

    /**
     * 尝试捕获异常
     *
     * @param Exception $exception
     * @return mixed
     * @throws Exception
     */
    protected function tryCatch(\Exception $exception)
    {
        if ($this->stack->isEmpty()) {
            throw $exception;
        }

        try {
            $generator = $this->stack->pop();
            $generator->throw($exception);
            return $this->getResult($generator);
        } catch (\Exception $e) {
            $this->tryCatch($e);
        }
    }

    /**
     * 获取协程函数返回的数据,php7获取return数据,php7以下使用第二次yield
     *
     * @param Generator $generator
     * @return mixed
     */
    protected function getResult(Generator $generator)
    {
        return $this->isPhp7
            ? $generator->getReturn()
            : $generator->current();
    }
}
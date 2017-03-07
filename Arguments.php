<?php
namespace Ant\Middleware;

/**
 * 在中间件中的传递参数
 *
 * Class Arguments
 * @package Ant\Middleware
 */
class Arguments
{
    private $arguments;

    /**
     * 注册传递的参数
     */
    public function __construct()
    {
        $this->arguments = func_get_args();
    }

    /**
     * 获取参数
     *
     * @return array
     */
    public function toArray()
    {
        return $this->arguments;
    }
}
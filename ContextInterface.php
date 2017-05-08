<?php
namespace Ant\Middleware;


interface ContextInterface
{
    public function set($key, $value);
    public function get($key, $default = null);
    public function has($key);
    public function remove($key);
    public function replace(array $items);
}
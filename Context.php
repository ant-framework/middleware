<?php
namespace Ant\Middleware;

use Countable;
use ArrayAccess;
use IteratorAggregate;

class Context implements ContextInterface, ArrayAccess, Countable, IteratorAggregate
{
    protected $items = [];

    public function __construct(array $items = [])
    {
        $this->replace($items);
    }

    public function set($key, $value)
    {
        $this->items[$key] = $value;
    }

    public function get($key, $default = null)
    {
        return $this->has($key) ? $this->items[$key] : $default;
    }

    public function has($key)
    {
        return array_key_exists($key, $this->items);
    }

    public function remove($key)
    {
        if($this->has($key)){
            unset($this->items[$key]);
        }
    }

    public function replace(array $items)
    {
        foreach($items as $offset => $item){
            $this->set($offset,$item);
        }
    }

    public function reset()
    {
        $this->items = [];
    }

    public function toArray()
    {
        return $this->items;
    }

    public function __set($name,$value)
    {
        $this->set($name,$value);
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __isset($name)
    {
        return $this->has($name);
    }

    public function __unset($name)
    {
        $this->remove($name);
    }

    public function count()
    {
        return count($this->items);
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->items);
    }

    public function offsetSet($offset, $value)
    {
        $this->set($offset,$value);
    }

    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }
}
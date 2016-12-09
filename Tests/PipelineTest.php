<?php
namespace Ant\Middleware;

use Ant\Middleware\Pipeline;
use Ant\Middleware\Arguments;

class PipelineTest extends \PHPUnit_Framework_TestCase
{
    public function testPipeline()
    {
        if(version_compare(PHP_VERSION, '7.0.0', '>=')){
            //Get Return Value
        }else{
            //Get Yield Value
        }
    }
}

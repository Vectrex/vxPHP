<?php

namespace vxPHP\Tests\Constraint;

use vxPHP\Routing\Route;
use PHPUnit\Framework\TestCase;

class RouteTest extends TestCase {
    public function testConstructor()
    {
        $route = new Route('foo', 'index.php');
        $this->assertEquals('foo', $route->getRouteId());
        $this->assertEquals('index.php', $route->getScriptName());
    }

    public function testSetRequestMethods()
    {
        $route = new Route('foo', 'index.php');
        $this->assertEquals([], $route->getRequestMethods());

        $route->setRequestMethods(['get', 'post']);
        $this->assertEquals(['GET', 'POST'], $route->getRequestMethods());

        $this->expectException('InvalidArgumentException');
        $route->setRequestMethods(['foo']);
    }

    public function testAllowsRequestMethod()
    {
        $route = new Route('foo', 'index.php', ['requestMethods' => ['get', 'post']]);
        $this->assertTrue($route->allowsRequestMethod('get'));
        $this->assertFalse($route->allowsRequestMethod('delete'));
    }

    public function testHasRelativePath()
    {
        $route = new Route('foo', 'index.php', ['path' => 'another/place']);
        $this->assertTrue($route->hasRelativePath());
        $route = new Route('foo', 'index.php', ['path' => '/another/place']);
        $this->assertFalse($route->hasRelativePath());
    }

}
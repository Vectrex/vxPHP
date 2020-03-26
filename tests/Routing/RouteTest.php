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
        $this->assertEquals(Route::KNOWN_REQUEST_METHODS, $route->getRequestMethods());
        $route = new Route('foo', 'index.php', ['requestMethods' => 'get']);
        $this->assertEquals(['GET'], $route->getRequestMethods());
        $this->expectException('InvalidArgumentException');
        new Route('foo', 'index.php', ['requestMethods' => 'foo']);
    }

    public function testSetRequestMethods()
    {
        $route = new Route('foo', 'index.php');
        $this->assertEquals(Route::KNOWN_REQUEST_METHODS, $route->getRequestMethods());

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
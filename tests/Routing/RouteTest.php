<?php

namespace vxPHP\Tests\Routing;

use vxPHP\Http\Request;
use vxPHP\Http\Response;
use vxPHP\Routing\Route;
use PHPUnit\Framework\TestCase;
use vxPHP\Routing\Router;

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

    public function testConstructorWithPlaceHolders ()
    {
        $this->expectException('InvalidArgumentException');
        new Route('foo', 'index.php', ['path' => 'foo/{bar}', 'placeholders' => [['match' => '[1-9][0-9]*']]]);
    }

    public function testConstructorWithInvalidPlaceholderMatch ()
    {
        $this->expectException('InvalidArgumentException');
        new Route('foo', 'index.php', ['path' => 'foo/{bar}', 'placeholders' => [['name' => 'bar', 'match' => '[1-9][0-9*']]]);
    }

    public function testSetPathParameterValue ()
    {
        $route = new Route('foo', 'index.php', ['path' => 'foo/{bar}', 'placeholders' => [['name' => 'bar', 'match' => '[1-9][0-9]*']]]);
        $route->setPathParameter('bar', '123');
        $this->assertEquals('123', $route->getPathParameter('bar'));
        $this->expectException('InvalidArgumentException');
        $route->setPathParameter('bar', 'abc');
    }

    public function testSetPathParameterName ()
    {
        $route = new Route('foo', 'index.php', ['path' => 'foo/{bar}', 'placeholders' => [['name' => 'bar', 'match' => '[1-9][0-9]*']]]);
        $this->expectException('InvalidArgumentException');
        $route->setPathParameter('baz', '123');
    }

    public function testGetPlaceholderByIndex ()
    {
        $route = new Route('foo', 'index.php', ['path' => 'foo/{bar}/{baz}', 'placeholders' => [['name' => 'bar', 'match' => '[1-9][0-9]*']]]);
        $this->assertEquals('baz', $route->getPlaceHolderByIndex(1)['name']);
        $this->expectException('InvalidArgumentException');
        $route->getPlaceHolderByIndex(2);
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

    public function testGetMatchExpression()
    {
        $route = new Route('foo', 'index.php');
        $this->assertEquals('foo', $route->getMatchExpression()); // match is set to id
        $route = new Route('foo', 'index.php', ['path' => 'foo/bar']);
        $this->assertEquals('foo/bar', $route->getMatchExpression());
    }

    public function testGetMethodName()
    {
        $route = new Route('foo', 'index.php');
        $this->assertNull($route->getMethodName());
        $route = new Route('foo', 'index.php', ['method' => 'bar']);
        $this->assertEquals('bar', $route->getMethodName());
        $route = new Route('foo', 'index.php');
        $route->setMethodName('bar');
        $this->assertEquals('bar', $route->getMethodName());
    }
    public function testGetControllerClassName()
    {
        $route = new Route('foo', 'index.php');
        $this->assertNull($route->getControllerClassName());
        $route = new Route('foo', 'index.php', ['controller' => 'bar']);
        $this->assertEquals('bar', $route->getControllerClassName());
        $route = new Route('foo', 'index.php');
        $route->setControllerClassName('bar');
        $this->assertEquals('bar', $route->getControllerClassName());
    }

    public function testGetPlaceholderNames()
    {
        $route = new Route('foo', 'index.php');
        $this->assertEquals([], $route->getPlaceholderNames());
        $route = new Route('foo', 'index.php', ['path' => 'foo/{bar}/{baz}']);
        $this->assertEquals(['bar', 'baz'], $route->getPlaceholderNames());
    }

    public function testGetPath()
    {
        $route = new Route('foo', 'index.php', ['path' => '/another/place']);
        $this->assertEquals('another/place', $route->getPath());
        $route = new Route('foo', 'index.php', ['path' => 'another/place']);
        $this->assertEquals('another/place', $route->getPath());
    }

    public function testGetPathParameter()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_URI'] = '/index.php/bar/abc';
        $_SERVER['PATH_INFO'] = '/bar/abc';
        $request = Request::createFromGlobals();

        $route = new Route('foo', 'index.php', ['path' => 'bar/{baz}']);
        $router = new Router();
        $router->addRoute($route);
        $route = $router->getRouteFromPathInfo($request);
        $this->assertEquals('abc', $route->getPathParameter('baz'));
        $this->assertEquals(null, $route->getPathParameter('foo'));
        $this->assertEquals('def', $route->getPathParameter('foo', 'def'));
    }

    public function testGetUrlMissingRouterException()
    {
        $route = new Route('foo', 'index.php', ['path' => 'bar/{baz}']);
        $this->expectException('RuntimeException');
        $route->getUrl(['baz' => 'thx1138']);
    }
    public function testGetUrlNoPrefixingException()
    {
        $route = new Route('foo', 'index.php', ['path' => '/bar/{baz}']);
        $router = new Router();
        $router->addRoute($route);
        $this->expectException('RuntimeException');
        $route->getUrl(['baz' => 'thx1138'], 'mypath');
    }
    public function testGetUrl()
    {
        $route = new Route('foo', 'index.php', ['path' => '/bar/{baz}']);
        $router = new Router();
        $router->addRoute($route);
        $this->assertEquals('/index.php/bar/thx1138', $route->getUrl(['baz' => 'thx1138']));

        $route = new Route('foo', 'index.php', ['path' => 'bar/{baz}']);
        $router = new Router();
        $router->addRoute($route);
        $this->assertEquals('/mypath/index.php/bar/thx1138', $route->getUrl(['baz' => 'thx1138'], 'mypath'));
    }

    public function testGetRedirect()
    {
        $this->assertEquals(true, true);
    }

    public function testRedirect()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_URI'] = '/index.php/another/place';
        $_SERVER['PATH_INFO'] = '/another/place';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['HTTPS'] = 'ON';

        $route = new Route('foo', 'index.php', ['path' => '/another/place']);
        $router = new Router();
        $router->addRoute($route);

        $response = $route->redirect();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(302, $response->getStatusCode());
        $response = $route->redirect([], 303);
        $this->assertEquals(303, $response->getStatusCode());
        $response = $route->redirect(['foo' => 'bar']);
        $this->assertEquals('https://localhost/index.php/?foo=bar', $response->getTargetUrl());

        $route = new Route('foo', 'index.php', ['path' => '/another/place']);
        $this->expectException('RuntimeException');
        $route->redirect();
    }
}

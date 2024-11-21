<?php

namespace Routing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use vxPHP\Http\Request;
use vxPHP\Routing\Route;
use vxPHP\Routing\Router;

class RouterTest extends TestCase
{
    public function testConstructor(): void
    {
        $router = new Router();
        $this->assertFalse($router->getServerSideRewrite());

        $_SERVER['REQUEST_URI'] = 'http://localhost/index.php/foo';
        $_SERVER['SCRIPT_NAME'] = 'index.php';
        $router = new Router();
        $this->assertFalse($router->getServerSideRewrite());

        $_SERVER['REQUEST_URI'] = 'http://localhost/foo';
        $_SERVER['SCRIPT_NAME'] = 'index.php';
        $router = new Router();
        $this->assertFalse($router->getServerSideRewrite()); // PHP_SAPI will detect CLI
    }

    public static function validRoutes(): array
    {
        return [
            [new Route('route00', 'index.php', ['path' => 'foo'])],
            [new Route('route01', 'index.php', ['path' => 'foo'])],
            [new Route('route02', 'index.php', ['path' => 'foo'])],
            [new Route('route03', 'index.php', ['path' => 'foo'])]
        ];
    }

    #[DataProvider('validRoutes')]
    public function testAddRoute(Route $route): void
    {
        $router = new Router();
        $id = $route->getRouteId();
        $router->addRoute($route);
        $this->assertEquals($route, $router->getRoute($id));
    }

    public function testRemoveRoute(): void
    {
        $router = new Router();
        $router->addRoute(new Route('route00', 'index.php'));
        $this->assertCount(1, $router->getRoutes());
        $router->removeRoute('route00');
        $this->assertCount(0, $router->getRoutes());
        $this->expectException('RuntimeException');
        $router->getRoute('route00');
    }

    public function getServerSideRewrite(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['SCRIPT_FILENAME'] = '/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/index.php/foo/abc';
        $_SERVER['PATH_INFO'] = '/foo/abc';

        $router = new Router();
        $this->assertFalse($router->getServerSideRewrite()); // will always return false since there is no server environment
    }

    public static function routesAndRequests(): array
    {
        $routes = [
            new Route('route00', 'index.php', ['path' => '/foo/{bar}', 'requestMethods' => ['get', 'post']]),
            new Route('route01', 'index.php', ['path' => 'foo', 'requestMethods' => ['get']]),
            new Route('route02', 'index.php', ['path' => 'foo/{bar=xyz}', 'requestMethods' => ['post']]),
            new Route('route03', 'index.php', [
                'path' => '/abc/{text}/{num}',
                'requestMethods' => ['get'],
                'placeholders' => [
                    ['name' => 'num', 'match' => '[0-9]+'],
                    ['name' => 'text', 'match' => '[a-zA-Z]+']
                ]
            ]),
            new Route('route04', 'index.php', [
                'path' => '/abc/{num}/{text}',
                'requestMethods' => ['get'],
                'placeholders' => [
                    ['name' => 'num', 'match' => '[0-9]+'],
                    ['name' => 'text', 'match' => '[a-zA-Z]+', 'default' => '']
                ]
            ]),
            new Route('route05', 'index.php', [
                'path' => '/abc/{num}',
                'requestMethods' => ['get'],
                'placeholders' => [
                    ['name' => 'num', 'match' => '[0-9]+']
                ]
            ]),
            new Route('route06', 'foo.php', [
                'path' => '/abc/{num}',
                'requestMethods' => ['get'],
                'placeholders' => [
                    ['name' => 'num', 'match' => '[0-9]+']
                ]
            ]),
            new Route('route07', 'foo.php', [
                'path' => '/abc/{num}',
                'requestMethods' => ['get'],
                'placeholders' => [
                    ['name' => 'num', 'match' => '[a-zA-z-]+']
                ]
            ]),
        ];

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['SCRIPT_FILENAME'] = '/index.php';

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/index.php/foo/abc';
        $_SERVER['PATH_INFO'] = '/foo/abc';

        $req1 = Request::createFromGlobals();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/index.php/foo';
        $_SERVER['PATH_INFO'] = '/foo';

        $req2 = Request::createFromGlobals();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/index.php/foo';
        $_SERVER['PATH_INFO'] = '/foo';

        $req3 = Request::createFromGlobals();

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/index.php/foo/abc';
        $_SERVER['PATH_INFO'] = '/foo/abc';

        $req4 = Request::createFromGlobals();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/index.php/abc/abc/123';
        $_SERVER['PATH_INFO'] = '/abc/abc/123';

        $req5 = Request::createFromGlobals();

        $_SERVER['REQUEST_URI'] = '/index.php/abc/123';
        $_SERVER['PATH_INFO'] = '/abc/123';

        $req6 = Request::createFromGlobals();

        $_SERVER['REQUEST_URI'] = '/index.php/abc/123/abc';
        $_SERVER['PATH_INFO'] = '/abc/123/abc';

        $req7 = Request::createFromGlobals();

        $_SERVER['SCRIPT_NAME'] = '/foo.php';
        $_SERVER['SCRIPT_FILENAME'] = '/foo.php';

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/foo.php/abc/1';
        $_SERVER['PATH_INFO'] = '/abc/1';

        $req8 = Request::createFromGlobals();

        $_SERVER['REQUEST_URI'] = '/foo.php/abc/A-b-c';
        $_SERVER['PATH_INFO'] = '/abc/A-b-c';

        $req9 = Request::createFromGlobals();

        return [
            [$routes, $req1, 'route00'],
            [$routes, $req2, 'route01'],
            [$routes, $req3, 'route02'],
            [$routes, $req4, 'route02'], // matches 0 and 2 - latter one wins
            [$routes, $req5, 'route03'],
            [$routes, $req6, 'route05'],
            [$routes, $req7, 'route04'],
            [$routes, $req8, 'route06'],
            [$routes, $req9, 'route07'],
        ];
    }

    #[DataProvider('routesAndRequests')]
    public function testGetRouteFromPathInfo($routes, $request, $matchingId): void
    {
        $router = new Router($routes);

        $route = $router->getRouteFromPathInfo($request);
        $this->assertEquals($matchingId, $route->getRouteId());
    }

    public function testNoRoutesConfiguredException(): void
    {
        $router = new Router();

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['SCRIPT_FILENAME'] = '/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/index.php/foo/abc';
        $_SERVER['PATH_INFO'] = '/foo/abc';

        $request = Request::createFromGlobals();

        $this->expectException('RuntimeException');
        $router->getRouteFromPathInfo($request);
    }

    public function testNoRoutesForScriptConfiguredException(): void
    {
        $router = new Router([new Route('route', 'foo.php', ['path' => '/foo', 'requestMethods' => ['get', 'post']])]);

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['SCRIPT_FILENAME'] = '/index.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/index.php/foo/abc';
        $_SERVER['PATH_INFO'] = '/foo/abc';

        $request = Request::createFromGlobals();

        $this->expectException('RuntimeException');
        $router->getRouteFromPathInfo($request);
    }
}
<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Http;

use PHPUnit\Framework\TestCase;
use vxPHP\Http\Request;
use vxPHP\Http\StreamedResponse;

class StreamedResponseTest extends TestCase
{
    public function testConstructor(): void
    {
        $response = new StreamedResponse(function (): void {
            echo 'foo';
        }, 404, ['Content-Type' => 'text/plain']);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('text/plain', $response->headers->get('Content-Type'));
    }

    public function testPrepareWith11Protocol(): void
    {
        $response = new StreamedResponse(function (): void {
            echo 'foo';
        });
        $request = Request::create('/');
        $request->server->set('SERVER_PROTOCOL', 'HTTP/1.1');

        $response->prepare($request);

        $this->assertEquals('1.1', $response->getProtocolVersion());
        $this->assertNotEquals('chunked', $response->headers->get('Transfer-Encoding'), 'Apache assumes responses with a Transfer-Encoding header set to chunked to already be encoded.');
    }

    public function testPrepareWith10Protocol(): void
    {
        $response = new StreamedResponse(function (): void {
            echo 'foo';
        });
        $request = Request::create('/');
        $request->server->set('SERVER_PROTOCOL', 'HTTP/1.0');

        $response->prepare($request);

        $this->assertEquals('1.0', $response->getProtocolVersion());
        $this->assertNull($response->headers->get('Transfer-Encoding'));
    }

    public function testPrepareWithHeadRequest(): void
    {
        $response = new StreamedResponse(function (): void {
            echo 'foo';
        }, 200, ['Content-Length' => '123']);
        $request = Request::create('/', 'HEAD');

        $response->prepare($request);

        $this->assertSame('123', $response->headers->get('Content-Length'));
    }

    public function testPrepareWithCacheHeaders(): void
    {
        $response = new StreamedResponse(function (): void {
            echo 'foo';
        }, 200, ['Cache-Control' => 'max-age=600, public']);
        $request = Request::create('/');

        $response->prepare($request);
        $this->assertEquals('max-age=600, public', $response->headers->get('Cache-Control'));
    }

    public function testSendContent(): void
    {
        $called = 0;

        $response = new StreamedResponse(function () use (&$called): void {
            ++$called;
        });

        $response->sendContent();
        $this->assertEquals(1, $called);

        $response->sendContent();
        $this->assertEquals(1, $called);
    }

    public function testSendContentWithNonCallable(): void
    {
        $this->expectException('LogicException');
        $response = new StreamedResponse(null);
        $response->sendContent();
    }

    public function testSetContent(): void
    {
        $this->expectException('LogicException');
        $response = new StreamedResponse(function (): void {
            echo 'foo';
        });
        $response->setContent('foo');
    }

    public function testGetContent(): void
    {
        $response = new StreamedResponse(function (): void {
            echo 'foo';
        });
        $this->assertFalse($response->getContent());
    }

    public function testCreate(): void
    {
        $response = StreamedResponse::create(function (): void {
        }, 204);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testReturnThis(): void
    {
        $response = new StreamedResponse(function (): void {
        });
        $this->assertInstanceOf(StreamedResponse::class, $response->sendContent());
        $this->assertInstanceOf(StreamedResponse::class, $response->sendContent());

        $response = new StreamedResponse(function (): void {
        });
        $this->assertInstanceOf(StreamedResponse::class, $response->sendHeaders());
        $this->assertInstanceOf(StreamedResponse::class, $response->sendHeaders());
    }

    public function testSetNotModified(): void
    {
        $response = new StreamedResponse(function (): void {
            echo 'foo';
        });
        $modified = $response->setNotModified();
        $this->assertObjectHasProperty('headers', $modified);
        $this->assertObjectHasProperty('content', $modified);
        $this->assertObjectHasProperty('version', $modified);
        $this->assertObjectHasProperty('statusCode', $modified);
        $this->assertObjectHasProperty('statusText', $modified);
        $this->assertObjectHasProperty('charset', $modified);
        $this->assertEquals(304, $modified->getStatusCode());

        ob_start();
        $modified->sendContent();
        $string = ob_get_clean();
        $this->assertEmpty($string);
    }
}

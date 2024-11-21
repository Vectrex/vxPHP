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
use vxPHP\Http\JsonResponse;

class JsonResponseTest extends TestCase
{
    public function testConstructorEmptyCreatesJsonObject(): void
    {
        $response = new JsonResponse();
        $this->assertSame('{}', $response->getContent());
    }

    public function testConstructorWithArrayCreatesJsonArray(): void
    {
        $response = new JsonResponse([0, 1, 2, 3]);
        $this->assertSame('[0,1,2,3]', $response->getContent());
    }

    public function testConstructorWithAssocArrayCreatesJsonObject(): void
    {
        $response = new JsonResponse(['foo' => 'bar']);
        $this->assertSame('{"foo":"bar"}', $response->getContent());
    }

    public function testConstructorWithSimpleTypes(): void
    {
        $response = new JsonResponse('foo');
        $this->assertSame('"foo"', $response->getContent());

        $response = new JsonResponse(0);
        $this->assertSame('0', $response->getContent());

        $response = new JsonResponse(0.1);
        $this->assertEquals(0.1, $response->getContent());
        $this->assertIsString($response->getContent());

        $response = new JsonResponse(true);
        $this->assertSame('true', $response->getContent());
    }

    public function testConstructorWithCustomStatus(): void
    {
        $response = new JsonResponse([], 202);
        $this->assertSame(202, $response->getStatusCode());
    }

    public function testConstructorAddsContentTypeHeader(): void
    {
        $response = new JsonResponse();
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
    }

    public function testConstructorWithCustomHeaders(): void
    {
        $response = new JsonResponse([], 200, ['ETag' => 'foo']);
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame('foo', $response->headers->get('ETag'));
    }

    public function testConstructorWithCustomContentType(): void
    {
        $headers = ['Content-Type' => 'application/vnd.acme.blog-v1+json'];

        $response = new JsonResponse([], 200, $headers);
        $this->assertSame('application/vnd.acme.blog-v1+json', $response->headers->get('Content-Type'));
    }

    public function testSetJson(): void
    {
        $response = new JsonResponse('1', 200, [], true);
        $this->assertEquals('1', $response->getContent());

        $response = new JsonResponse('[1]', 200, [], true);
        $this->assertEquals('[1]', $response->getContent());

        $response = new JsonResponse(null, 200, []);
        $response->setJson('true');
        $this->assertEquals('true', $response->getContent());
    }

    public function testCreate(): void
    {
        $response = JsonResponse::create(['foo' => 'bar'], 204);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('{"foo":"bar"}', $response->getContent());
        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testStaticCreateEmptyJsonObject(): void
    {
        $response = JsonResponse::create();
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{}', $response->getContent());
    }

    public function testStaticCreateJsonArray(): void
    {
        $response = JsonResponse::create([0, 1, 2, 3]);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('[0,1,2,3]', $response->getContent());
    }

    public function testStaticCreateJsonObject(): void
    {
        $response = JsonResponse::create(['foo' => 'bar']);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('{"foo":"bar"}', $response->getContent());
    }

    public function testStaticCreateWithSimpleTypes(): void
    {
        $response = JsonResponse::create('foo');
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('"foo"', $response->getContent());

        $response = JsonResponse::create(0);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('0', $response->getContent());

        $response = JsonResponse::create(0.1);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(0.1, $response->getContent());
        $this->assertIsString($response->getContent());

        $response = JsonResponse::create(true);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame('true', $response->getContent());
    }

    public function testStaticCreateWithCustomStatus(): void
    {
        $response = JsonResponse::create([], 202);
        $this->assertSame(202, $response->getStatusCode());
    }

    public function testStaticCreateAddsContentTypeHeader(): void
    {
        $response = JsonResponse::create();
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
    }

    public function testStaticCreateWithCustomHeaders(): void
    {
        $response = JsonResponse::create([], 200, ['ETag' => 'foo']);
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame('foo', $response->headers->get('ETag'));
    }

    public function testStaticCreateWithCustomContentType(): void
    {
        $headers = ['Content-Type' => 'application/vnd.acme.blog-v1+json'];

        $response = JsonResponse::create([], 200, $headers);
        $this->assertSame('application/vnd.acme.blog-v1+json', $response->headers->get('Content-Type'));
    }

    public function testJsonEncodeFlags(): void
    {
        $response = new JsonResponse('<>\'&"');

        $this->assertEquals('"\u003C\u003E\u0027\u0026\u0022"', $response->getContent());
    }

    public function testGetEncodingOptions(): void
    {
        $response = new JsonResponse();

        $this->assertEquals(JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT, $response->getEncodingOptions());
    }

    public function testSetEncodingOptions(): void
    {
        $response = new JsonResponse();
        $response->setData([[1, 2, 3]]);

        $this->assertEquals('[[1,2,3]]', $response->getContent());

        $response->setEncodingOptions(JSON_FORCE_OBJECT);

        $this->assertEquals('{"0":{"0":1,"1":2,"2":3}}', $response->getContent());
    }

    public function testItAcceptsJsonAsString(): void
    {
        $response = JsonResponse::fromJsonString('{"foo":"bar"}');
        $this->assertSame('{"foo":"bar"}', $response->getContent());
    }

    public function testSetContent(): void
    {
        $this->expectException('InvalidArgumentException');
        JsonResponse::create("\xB1\x31");
    }

    public function testSetContentJsonSerializeError(): void
    {
        $this->expectException('Exception');
        $this->expectExceptionMessage('This error is expected');
        if (!interface_exists('JsonSerializable', false)) {
            $this->markTestSkipped('JsonSerializable is required.');
        }

        $serializable = new JsonSerializableObject();

        JsonResponse::create($serializable);
    }
}

if (interface_exists('JsonSerializable', false)) {
    class JsonSerializableObject implements \JsonSerializable
    {
        #[\ReturnTypeWillChange]
        public function jsonSerialize(): void
        {
            throw new \Exception('This error is expected');
        }
    }
}

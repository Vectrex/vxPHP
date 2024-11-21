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
use vxPHP\Http\RedirectResponse;

class RedirectResponseTest extends TestCase
{
    public function testGenerateMetaRedirect(): void
    {
        $response = new RedirectResponse('foo.bar');

        $this->assertMatchesRegularExpression('#<meta http-equiv="refresh" content="\d+;url=\'foo\.bar\'" />#', preg_replace('/\s+/', ' ', $response->getContent()));
    }

    public function testRedirectResponseConstructorEmptyUrl(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Cannot redirect to an empty URL.');
        new RedirectResponse('');
    }

    public function testRedirectResponseConstructorWrongStatusCode(): void
    {
        $this->expectException('InvalidArgumentException');
        new RedirectResponse('foo.bar', 404);
    }

    public function testGenerateLocationHeader(): void
    {
        $response = new RedirectResponse('foo.bar');

        $this->assertTrue($response->headers->has('Location'));
        $this->assertEquals('foo.bar', $response->headers->get('Location'));
    }

    public function testGetTargetUrl(): void
    {
        $response = new RedirectResponse('foo.bar');

        $this->assertEquals('foo.bar', $response->getTargetUrl());
    }

    public function testSetTargetUrl(): void
    {
        $response = new RedirectResponse('foo.bar');
        $response->setTargetUrl('baz.beep');

        $this->assertEquals('baz.beep', $response->getTargetUrl());
    }

    public function testSetTargetUrlNull(): void
    {
        $this->expectException('InvalidArgumentException');
        $response = new RedirectResponse('foo.bar');
        $response->setTargetUrl(null);
    }

    public function testCreate(): void
    {
        $response = RedirectResponse::create('foo', 301);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(301, $response->getStatusCode());
    }

    public function testCacheHeaders(): void
    {
        $response = new RedirectResponse('foo.bar', 301);
        $this->assertFalse($response->headers->hasCacheControlDirective('no-cache'));

        $response = new RedirectResponse('foo.bar', 301, ['cache-control' => 'max-age=86400']);
        $this->assertFalse($response->headers->hasCacheControlDirective('no-cache'));
        $this->assertTrue($response->headers->hasCacheControlDirective('max-age'));

        $response = new RedirectResponse('foo.bar', 301, ['Cache-Control' => 'max-age=86400']);
        $this->assertFalse($response->headers->hasCacheControlDirective('no-cache'));
        $this->assertTrue($response->headers->hasCacheControlDirective('max-age'));

        $response = new RedirectResponse('foo.bar', 302);
        $this->assertTrue($response->headers->hasCacheControlDirective('no-cache'));
    }
}

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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use vxPHP\Http\Cookie;

/**
 * CookieTest.
 *
 * @author John Kary <john@johnkary.net>
 * @author Hugo Hamon <hugo.hamon@sensio.com>
 */
#[Group('time-sensitive')]
class CookieTest extends TestCase
{
    public static function namesWithSpecialCharacters(): array
    {
        return [
            [',MyName'],
            [';MyName'],
            [' MyName'],
            ["\tMyName"],
            ["\rMyName"],
            ["\nMyName"],
            ["\013MyName"],
            ["\014MyName"],
        ];
    }

    #[DataProvider('namesWithSpecialCharacters')]
    public function testInstantiationThrowsExceptionIfRawCookieNameContainsSpecialCharacters($name): void
    {
        $this->expectException('InvalidArgumentException');
        Cookie::create($name, null, 0, null, null, null, false, true);
    }

    #[DataProvider('namesWithSpecialCharacters')]
    public function testInstantiationSucceedNonRawCookieNameContainsSpecialCharacters($name): void
    {
        $this->assertInstanceOf(Cookie::class, Cookie::create($name));
    }

    public function testInstantiationThrowsExceptionIfCookieNameIsEmpty(): void
    {
        $this->expectException('InvalidArgumentException');
        Cookie::create('');
    }

    public function testInvalidExpiration(): void
    {
        $this->expectException('InvalidArgumentException');
        Cookie::create('MyCookie', 'foo', 'bar');
    }

    public function testNegativeExpirationIsNotPossible(): void
    {
        $cookie = Cookie::create('foo', 'bar', -100);

        $this->assertSame(0, $cookie->getExpiresTime());
    }

    public function testGetValue(): void
    {
        $value = 'MyValue';
        $cookie = Cookie::create('MyCookie', $value);

        $this->assertSame($value, $cookie->getValue(), '->getValue() returns the proper value');
    }

    public function testGetPath(): void
    {
        $cookie = Cookie::create('foo', 'bar');

        $this->assertSame('/', $cookie->getPath(), '->getPath() returns / as the default path');
    }

    public function testGetExpiresTime(): void
    {
        $cookie = Cookie::create('foo', 'bar');

        $this->assertEquals(0, $cookie->getExpiresTime(), '->getExpiresTime() returns the default expire date');

        $cookie = Cookie::create('foo', 'bar', $expire = time() + 3600);

        $this->assertEquals($expire, $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date');
    }

    public function testGetExpiresTimeIsCastToInt(): void
    {
        $cookie = Cookie::create('foo', 'bar', 3600.9);

        $this->assertSame(3600, $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date as an integer');
    }

    public function testConstructorWithDateTime(): void
    {
        $expire = new \DateTime();
        $cookie = Cookie::create('foo', 'bar', $expire);

        $this->assertEquals($expire->format('U'), $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date');
    }

    public function testConstructorWithDateTimeImmutable(): void
    {
        $expire = new \DateTimeImmutable();
        $cookie = Cookie::create('foo', 'bar', $expire);

        $this->assertEquals($expire->format('U'), $cookie->getExpiresTime(), '->getExpiresTime() returns the expire date');
    }

    public function testGetExpiresTimeWithStringValue(): void
    {
        $value = '+1 day';
        $cookie = Cookie::create('foo', 'bar', $value);
        $expire = strtotime($value);

        $this->assertEqualsWithDelta($expire, $cookie->getExpiresTime(), 1, '->getExpiresTime() returns the expire date');
    }

    public function testGetDomain(): void
    {
        $cookie = Cookie::create('foo', 'bar', 0, '/', '.myfoodomain.com');

        $this->assertEquals('.myfoodomain.com', $cookie->getDomain(), '->getDomain() returns the domain name on which the cookie is valid');
    }

    public function testIsSecure(): void
    {
        $cookie = Cookie::create('foo', 'bar', 0, '/', '.myfoodomain.com', true);

        $this->assertTrue($cookie->isSecure(), '->isSecure() returns whether the cookie is transmitted over HTTPS');
    }

    public function testIsHttpOnly(): void
    {
        $cookie = Cookie::create('foo', 'bar', 0, '/', '.myfoodomain.com', false);

        $this->assertTrue($cookie->isHttpOnly(), '->isHttpOnly() returns whether the cookie is only transmitted over HTTP');
    }

    public function testCookieIsNotCleared(): void
    {
        $cookie = Cookie::create('foo', 'bar', time() + 3600 * 24);

        $this->assertFalse($cookie->isCleared(), '->isCleared() returns false if the cookie did not expire yet');
    }

    public function testCookieIsCleared(): void
    {
        $cookie = Cookie::create('foo', 'bar', time() - 20);

        $this->assertTrue($cookie->isCleared(), '->isCleared() returns true if the cookie has expired');

        $cookie = Cookie::create('foo', 'bar');

        $this->assertFalse($cookie->isCleared());

        $cookie = Cookie::create('foo', 'bar');

        $this->assertFalse($cookie->isCleared());

        $cookie = Cookie::create('foo', 'bar', -1);

        $this->assertFalse($cookie->isCleared());
    }

    public function testToString(): void
    {
        $cookie = Cookie::create('foo', 'bar', strtotime('Fri, 20-May-2011 15:25:52 GMT'), '/', '.myfoodomain.com', true, true, false, null);
        $this->assertEquals('foo=bar; expires=Fri, 20-May-2011 15:25:52 GMT; Max-Age=0; path=/; domain=.myfoodomain.com; secure; httponly', (string)$cookie, '->__toString() returns string representation of the cookie');

        $cookie = Cookie::create('foo', 'bar with white spaces', strtotime('Fri, 20-May-2011 15:25:52 GMT'), '/', '.myfoodomain.com', true, true, false, null);
        $this->assertEquals('foo=bar%20with%20white%20spaces; expires=Fri, 20-May-2011 15:25:52 GMT; Max-Age=0; path=/; domain=.myfoodomain.com; secure; httponly', (string)$cookie, '->__toString() encodes the value of the cookie according to RFC 3986 (white space = %20)');

        $cookie = Cookie::create('foo', null, 1, '/admin/', '.myfoodomain.com', false, true, false, null);
        $this->assertEquals('foo=deleted; expires=' . gmdate('D, d-M-Y H:i:s T', time() - 31536001) . '; Max-Age=0; path=/admin/; domain=.myfoodomain.com; httponly', (string)$cookie, '->__toString() returns string representation of a cleared cookie if value is NULL');

        $cookie = Cookie::create('foo', 'bar');
        $this->assertEquals('foo=bar; path=/; httponly; samesite=lax', (string)$cookie);
    }

    public function testRawCookie(): void
    {
        $cookie = Cookie::create('foo', 'b a r', 0, '/', null, false, false, false, null);
        $this->assertFalse($cookie->isRaw());
        $this->assertEquals('foo=b%20a%20r; path=/', (string)$cookie);

        $cookie = Cookie::create('foo', 'b+a+r', 0, '/', null, false, false, true, null);
        $this->assertTrue($cookie->isRaw());
        $this->assertEquals('foo=b+a+r; path=/', (string)$cookie);
    }

    public function testGetMaxAge(): void
    {
        $cookie = Cookie::create('foo', 'bar');
        $this->assertEquals(0, $cookie->getMaxAge());

        $cookie = Cookie::create('foo', 'bar', $expire = time() + 100);
        $this->assertEquals($expire - time(), $cookie->getMaxAge());

        $cookie = Cookie::create('foo', 'bar', time() - 100);
        $this->assertEquals(0, $cookie->getMaxAge());
    }

    public function testFromString(): void
    {
        $cookie = Cookie::fromString('foo=bar; expires=Fri, 20-May-2011 15:25:52 GMT; path=/; domain=.myfoodomain.com; secure; httponly');
        $this->assertEquals(Cookie::create('foo', 'bar', strtotime('Fri, 20-May-2011 15:25:52 GMT'), '/', '.myfoodomain.com', true, true, true, null), $cookie);

        $cookie = Cookie::fromString('foo=bar', true);
        $this->assertEquals(Cookie::create('foo', 'bar', 0, '/', null, false, false, false, null), $cookie);

        $cookie = Cookie::fromString('foo', true);
        $this->assertEquals(Cookie::create('foo', null, 0, '/', null, false, false, false, null), $cookie);
    }

    public function testFromStringWithHttpOnly(): void
    {
        $cookie = Cookie::fromString('foo=bar; expires=Fri, 20-May-2011 15:25:52 GMT; path=/; domain=.myfoodomain.com; secure; httponly');
        $this->assertTrue($cookie->isHttpOnly());

        $cookie = Cookie::fromString('foo=bar; expires=Fri, 20-May-2011 15:25:52 GMT; path=/; domain=.myfoodomain.com; secure');
        $this->assertFalse($cookie->isHttpOnly());
    }

    public function testSameSiteAttribute(): void
    {
        $cookie = new Cookie('foo', 'bar', 0, '/', null, false, true, false, 'Lax');
        $this->assertEquals('lax', $cookie->getSameSite());

        $cookie = new Cookie('foo', 'bar', 0, '/', null, false, true, false, '');
        $this->assertNull($cookie->getSameSite());
    }

    public function testSetSecureDefault(): void
    {
        $cookie = Cookie::create('foo', 'bar');

        $this->assertFalse($cookie->isSecure());

        $cookie->setSecureDefault(true);

        $this->assertTrue($cookie->isSecure());

        $cookie->setSecureDefault(false);

        $this->assertFalse($cookie->isSecure());
    }
}

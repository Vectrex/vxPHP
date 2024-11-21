<?php
/**
 * Created by PhpStorm.
 * User: gregor
 * Date: 28.11.17
 * Time: 21:36
 */

namespace Constraint\Validator;

use vxPHP\Constraint\Validator\Ip;
use PHPUnit\Framework\TestCase;

class IpTest extends TestCase
{
    protected array $invalidIpV4 = [
        '0', '0.0', '0.0.0', '0.0.0.0.0', '256.0.0.0', '0.256.0.0', '0.0.256.0', '0.0.0.256', '-1.0.0.0', 'dummytext'
    ];

    protected array $validIpV4 = [
        '0.0.0.0', '10.0.0.0', '123.45.67.89', '98.76.54.32', '192.168.0.1', '127.0.0.1', '255.255.255.255'
    ];

    protected array $validIpV6 = [
        '1200:0000:AB00:1234:0000:2552:7777:1313',
        '21DA:D3:0:2F3B:2AA:FF:FE28:9C5A',
        '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
        '2001:0DB8:85A3:0000:0000:8A2E:0370:7334',
        '2001:0Db8:85a3:0000:0000:8A2e:0370:7334',
        'fdfe:dcba:9876:ffff:fdc6:c46b:bb8f:7d4c',
        'fdc6:c46b:bb8f:7d4c:fdc6:c46b:bb8f:7d4c',
        'fdc6:c46b:bb8f:7d4c:0000:8a2e:0370:7334',
        'fe80:0000:0000:0000:0202:b3ff:fe1e:8329',
        'fe80:0:0:0:202:b3ff:fe1e:8329',
        'fe80::202:b3ff:fe1e:8329',
        '0:0:0:0:0:0:0:0',
        '::',
        '0::',
        '::0',
        '0::0',
        '2001:0db8:85a3:0000:0000:8a2e:0.0.0.0',
        '::0.0.0.0',
        '::255.255.255.255',
        '::123.45.67.178',
    ];

    protected array $invalidIpV6 = [
        '42540766414390830568948465903729639425',
    ];

    public function testInvalidIpV4(): void
    {

        $v = new Ip('v4');

        foreach ($this->invalidIpV4 as $test) {
            $this->assertFalse($v->validate($test));
        }

    }

    public function testValidIpV4(): void
    {

        $v = new Ip('v4');

        foreach ($this->validIpV4 as $test) {
            $this->assertTrue($v->validate($test));
        }

    }

    public function testNullIsInvalid(): void
    {

        $this->assertFalse((new Ip())->validate(null));

    }

    public function testEmptyStringIsInvalid(): void
    {

        $this->assertFalse((new Ip())->validate(''));

    }

    public function testValidIpV6(): void
    {

        $v = new Ip('v6');

        foreach ($this->validIpV6 as $test) {
            $this->assertTrue($v->validate($test));
        }

    }

    public function testInvalidIpV6(): void
    {

        $v = new Ip('v6');

        foreach ($this->invalidIpV6 as $test) {
            $this->assertFalse($v->validate($test));
        }

    }
}

<?php
/**
 * Created by PhpStorm.
 * User: gregor
 * Date: 28.11.17
 * Time: 11:19
 */

namespace vxPHP\Constraint\Tests\Validator;

use vxPHP\Constraint\Validator\Ip;
use PHPUnit\Framework\TestCase;

class IpTest extends TestCase
{
    public function testValidate() {

        $v = new Ip();

        $this->assertsEquals(true, $v->validate('0.0.0.0'));

    }

}

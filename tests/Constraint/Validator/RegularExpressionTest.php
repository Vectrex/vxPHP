<?php
/**
 * Created by PhpStorm.
 * User: gregor
 * Date: 28.11.17
 * Time: 21:36
 */

namespace vxPHP\Tests\Constraint;

use PHPUnit\Framework\TestCase;
use vxPHP\Constraint\Validator\RegularExpression;

class RegularExpressionTest extends TestCase {

    public function testInvalidRegExp()
    {

        $this->expectException(\InvalidArgumentException::class);
        new RegularExpression('/^(/');

    }

    public function testRegExpMatches()
    {
        $v = new RegularExpression('/^foo$/i');

        $this->assertTrue($v->validate('Foo'));
        $this->assertFalse($v->validate(' foo'));

        $v = new RegularExpression('#bar#');

        $this->assertTrue($v->validate('bar'));
        $this->assertFalse($v->validate('Bar'));

    }

}

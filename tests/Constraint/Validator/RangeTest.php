<?php
/**
 * Created by PhpStorm.
 * User: gregor
 * Date: 28.11.17
 * Time: 21:36
 */

namespace Constraint\Validator;

use PHPUnit\Framework\TestCase;
use vxPHP\Constraint\Validator\Range;

class RangeTest extends TestCase
{
    public function testValidExclusiveRange(): void
    {
        $min = -10;
        $max = 20;
        $diff = 0.0001;

        $v = new Range($min, $max, ['exclusive' => true]);

        for ($i = $min + $diff; $i <= $max - $diff; $i += 0.1) {
            $this->assertTrue($v->validate($i));
        }
    }

    public function testValidInclusiveRange(): void
    {
        $min = -10;
        $max = 20;

        $v = new Range($min, $max);

        for ($i = $min; $i <= $max; $i += 0.1) {
            $this->assertTrue($v->validate($i));
        }
    }

    public function testInvalidExclusiveRange(): void
    {
        $min = -10;
        $max = 20;

        $v = new Range($min, $max, ['exclusive' => true]);

        foreach ([-11, -10.1, -10, 20, 20.1, 21] as $i) {
            $this->assertFalse($v->validate($i));
        }
    }

    public function testInvalidInclusiveRange(): void
    {
        $min = -10;
        $max = 20;

        $v = new Range($min, $max);

        foreach ([-11, -10.1, 20.1, 21] as $i) {
            $this->assertFalse($v->validate($i));
        }
    }

    public function testNonNumericValue(): void
    {
        $v = new Range(-10, 10);

        $this->assertFalse($v->validate('foo'));
        $this->assertFalse($v->validate('0x'));
        $this->assertFalse($v->validate('1.'));
        $this->assertFalse($v->validate('.1'));
    }
}

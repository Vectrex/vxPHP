<?php
namespace vxPHP\Tests\Constraint;

use PHPUnit\Framework\TestCase;
use vxPHP\Constraint\Validator\Time;

class TimeTest extends TestCase {

    protected $validTimes = [
        '1:0',
        '1:1',
        '1:1:1',
        '01:1',
        '01:1:0',
        '01:01:0',
        '01:01:01',
        '23:00:00',
        '23:59:59',
        ' 23:59:59 '
    ];

    protected $invalidTimes = [
        '0',
        '0:',
        '0:0:',
        '0.0.0',
        '0:0,0',
        '24:0',
        '24:0:0',
        '23:60',
        '23:59:60'
    ];

    public function testValidTimes()
    {
        $v = new Time();
        foreach($this->validTimes as $value) {
            $this->assertTrue($v->validate($value));
        }
    }

    public function testInvalidTimes()
    {
        $v = new Time();
        foreach($this->invalidTimes as $value) {
            $this->assertFalse($v->validate($value));
        }
    }
}

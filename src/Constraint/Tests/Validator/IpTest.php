<?php

namespace vxPHP\Constraint\Tests\Validator;

use PHPUnit\Framework\TestCase;
use vxPHP\Constraint\Validator\Ip;

class IpTest extends TestCase {
	
	public function testValidate() {
		
		$v = new Ip();
		
		$this->assertsEquals(TRUE, $v->validate('0.0.0.0'));

	}
	
}
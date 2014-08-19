<?php
namespace vxPHP\Orm;

use vxPHP\Orm\NestedSetInterface;

abstract class NestedSetBehaviour implements NestedSetInterface {
	protected	$level,
				$l,
				$r,
				$tableName;

	public function getLevel() {
		return $this->level;
	}

	public static function getRootInstance() {
		;
	}
}

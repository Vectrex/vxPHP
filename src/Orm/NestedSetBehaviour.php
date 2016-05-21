<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

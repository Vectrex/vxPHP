<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Webpage\MenuEntry;

use vxPHP\Webpage\MenuEntry\MenuEntry;

class DynamicMenuEntry extends MenuEntry {
	public function __construct($page, $attributes) {
		parent::__construct($page, $attributes);
	}

	public function setPage($page) {
		$this->page = $page;
	}

	public function setAttributes(Array $attributes) {
		foreach($attributes as $attr => $value) {
			$this->attributes->$attr = $value;
		}
	}

	public function __destruct() {
		parent::__destruct();
	}
}

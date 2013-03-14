<?php

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

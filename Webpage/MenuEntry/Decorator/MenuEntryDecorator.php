<?php

namespace vxPHP\Webpage\MenuEntry\Decorator;

use vxPHP\Webpage\MenuEntry\MenuEntryInterface;
use vxPHP\Webpage\MenuEntry\MenuEntry;

/**
 * abstract class for various menu entry decorators
 */
abstract class MenuEntryDecorator implements MenuEntryInterface {
	protected $menuEntry;
	protected static $href;

	public function __construct(MenuEntry $menuEntry) {
		$this->menuEntry = $menuEntry;
	}

	abstract public function render();
}

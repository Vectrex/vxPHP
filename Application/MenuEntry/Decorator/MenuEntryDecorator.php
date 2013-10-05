<?php

namespace vxPHP\Application\MenuEntry\Decorator;

use vxPHP\Application\MenuEntry\MenuEntryInterface;
use vxPHP\Application\MenuEntry\MenuEntry;

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

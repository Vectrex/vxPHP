<?php

namespace vxPHP\Application\Menu\Decorator;

use vxPHP\Application\Menu\Menu;
use vxPHP\Application\Menu\MenuInterface;

/**
 * abstract class for various menu decorators
 */
abstract class MenuDecorator implements MenuInterface {

	protected $menu;

	public function __construct(Menu $menu) {
		$this->menu = $menu;
	}

	public abstract function render($showSubmenus, $forceActive, $decoratorParameters);

}
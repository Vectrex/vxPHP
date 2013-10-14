<?php

namespace vxPHP\Webpage\Menu\Decorator;

use vxPHP\Webpage\Menu\Menu;
use vxPHP\Webpage\Menu\MenuInterface;

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
<?php

namespace vxPHP\Webpage\Menu\Decorator;

use vxPHP\Webpage\Menu\Menu;
use vxPHP\Webpage\Menu\Decorator\MenuDecoratorInterface;

/**
 * abstract class for various menu decorators
 */
abstract class MenuDecorator implements MenuDecoratorInterface {

	protected $menu;

	public function __construct(Menu $menu) {
		$this->menu = $menu;
	}

}
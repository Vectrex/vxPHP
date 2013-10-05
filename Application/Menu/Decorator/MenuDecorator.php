<?php

namespace vxPHP\Application\Menu\Decorator;

use vxPHP\Application\Menu\Menu;
use vxPHP\Application\Menu\Decorator\MenuDecoratorInterface;

/**
 * abstract class for various menu decorators
 */
abstract class MenuDecorator implements MenuDecoratorInterface {

	protected $menu;

	public function __construct(Menu $menu) {
		$this->menu = $menu;
	}

}
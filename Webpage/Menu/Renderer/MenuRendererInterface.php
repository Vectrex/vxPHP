<?php

namespace vxPHP\Webpage\Menu\Renderer;

use vxPHP\Webpage\Menu\Menu;
use vxPHP\Webpage\MenuEntry\MenuEntry;

interface MenuRendererInterface {

	/**
	 * convenience method; allow chaining of renderer instantiation parameter setting and rendering
	 *
	 * @param Menu $menu
	 * @return MenuRendererInterface
	 */
	public static function create(Menu $menu);

	/**
	 * set parameters required by renderer
	 *
	 * @param array $parameters
	 * @return MenuRendererInterface
	 */
	public function setParameters(Array $parameters);

	/**
	 * render menu with its menu entries
	 *
	 * @return string
	 */
	public function render();

}
<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Webpage\Menu\Renderer;

use vxPHP\Webpage\Menu\Menu;
use vxPHP\Webpage\MenuEntry\MenuEntry;

abstract class MenuRenderer implements MenuRendererInterface
{
	protected Menu $menu;
	protected array $parameters = [];

    /**
     * initialize menu renderer
     *
     * @param Menu $menu
     */
	public function __construct(Menu $menu)
    {
		$this->menu = $menu;
	}

    /**
     * /* (non-PHPdoc)
     * @see \vxPHP\Webpage\Menu\Renderer\MenuRendererInterface::create()
     * @param Menu $menu
     * @return MenuRenderer
     */
	public static function create(Menu $menu): MenuRenderer
    {
		return new static($menu);
	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Webpage\Menu\Renderer\MenuRendererInterface::setParameters()
     * @param array $parameters
     * @return MenuRenderer
	 */
	public function setParameters(array $parameters): MenuRenderer
    {
		$this->parameters = $parameters;
		return $this;
	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Webpage\Menu\Renderer\MenuRendererInterface::render()
     * @return string
	 */
	abstract public function render(): string;

    /**
     * render a single menu entry
     *
     * @param MenuEntry $entry
     * @return string
     */
	abstract protected function renderEntry(MenuEntry $entry): string;
}
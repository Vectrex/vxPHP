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

use vxPHP\Webpage\Menu\Renderer\MenuRendererInterface;
use vxPHP\Webpage\Menu\Menu;
use vxPHP\Webpage\MenuEntry\MenuEntry;
use vxPHP\Application\Application;

abstract class MenuRenderer implements MenuRendererInterface {

	protected $menu;
	protected $hasNiceUris;
	protected $parameters;

	/**
	 * initialize menu renderer
	 *
	 * @param Menu $menu
	 */
	public function __construct(Menu $menu) {

		$this->menu = $menu;
		$this->hasNiceUris = Application::getInstance()->hasNiceUris();

	}

	/**
	/* (non-PHPdoc)
	 * @see \vxPHP\Webpage\Menu\Renderer\MenuRendererInterface::create()
	 */
	public static function create(Menu $menu) {

		return new static($menu);

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Webpage\Menu\Renderer\MenuRendererInterface::setParameters()
	 */
	public function setParameters(Array $parameters) {

		$this->parameters = $parameters;
		return $this;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Webpage\Menu\Renderer\MenuRendererInterface::render()
	 */
	abstract public function render();

	/**
	 * render a single menu entry
	 *
	 * @param MenuEntry $entry
	 * @return string
	 */
	abstract protected function renderEntry(MenuEntry $e);

}
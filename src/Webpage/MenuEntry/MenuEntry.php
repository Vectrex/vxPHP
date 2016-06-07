<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Webpage\MenuEntry;

use vxPHP\Webpage\Menu\Menu;
use vxPHP\Application\Application;
use vxPHP\Routing\Route;
use vxPHP\Routing\Router;

/**
 * MenuEntry class
 * manages a single menu entry
 *
 * @version 0.3.7 2014-04-06
 */
class MenuEntry {
	
	/**
	 * the index counter of menu entries
	 * @var integer
	 */
	protected static $count = 1;

	/**
	 * the menu the menu entry belongs to
	 * @var Menu
	 */
	protected $menu;

	/**
	 * the authentication level of the entry
	 * @var string
	 */
	protected $auth;
	
	/**
	 * additional authentication parameter which
	 * might be required by the authentication level
	 * @var string
	 */
	protected $authParameters;
	
	/**
	 * misc attributes
	 * @var \stdClass
	 */
	protected $attributes;
	
	/**
	 * unique index of menu entry
	 * @var integer
	 */
	protected $ndx;
	
	/**
	 * the path of the menu entry 
	 * @var string
	 */
	protected $path;
	
	/**
	 * an optional submenu of the menu entry
	 * @var Menu
	 */
	protected $subMenu;
	
	/**
	 * flag indicating whether menu entry destination is an absoulte or relative URL
	 * @var boolean
	 */
	protected $localPage;
	
	/**
	 * the URL of the menu entry
	 * @var string
	 */
	protected $href;

	public function __construct($path, $attributes, $localPage = TRUE) {

		$this->ndx			= self::$count++;
		$this->path			= trim($path, '/');
		$this->localPage	= (boolean) $localPage;
		$this->attributes	= new \stdClass();

		foreach($attributes as $attr => $value) {
			$attr = strtolower($attr);
			$this->attributes->$attr = (string) $value;
		}
	}

	// purge dynamically generated submenus

	public function __destruct() {
		if($this->subMenu && $this->subMenu->getType() == 'dynamic') {
			$this->subMenu->__destruct();
		}
	}

	public function __toString() {
		return $this->path;
	}

	public function appendMenu(Menu $menu) {
		$menu->setParentEntry($this);
		$this->subMenu = $menu;
	}

	public function setMenu(Menu $menu) {
		$this->menu = $menu;
	}

	public function getMenu() {
		return $this->menu;
	}

	public function getAuth() {
		return $this->auth;
	}

	public function setAuth($auth) {
		$this->auth = $auth;
	}

	public function getAuthParameters() {
		return $this->authParameters;
	}

	public function setAuthParameters($authParameters) {
		$this->authParameters = $authParameters;
	}

	public function isAuthenticatedBy($privilege) {
		return isset($this->auth) && $privilege <= $this->auth;
	}

	public function refersLocalPage() {
		return $this->localPage;
	}

	public function select() {
		$this->menu->setSelectedEntry($this);
	}

	public function getSubMenu() {
		return $this->subMenu;
	}

	public function getPath() {
		return $this->path;
	}

	/**
	 * get href attribute value of menu entry
	 */
	public function getHref() {

		if(is_null($this->href)) {

			if($this->localPage) {

				$pathSegments = [];
				$e = $this;

				do {
					$pathSegments[] = $e->path;
				} while ($e = $e->menu->getParentEntry());

				if(Application::getInstance()->hasNiceUris()) {

					if(($script = basename($this->menu->getScript(), '.php')) == 'index') {
						$script = '/';
					}

					else {
						$script = '/'. $script . '/';
					}
				}

				else {
					$script = '/' . $this->menu->getScript() . '/';
				}

				$this->href = $script . implode('/', array_reverse($pathSegments));

			}

			else {

				$this->href = $this->path;

			}
		}

		return $this->href;
	}

	/**
	 * get all attributes
	 * @return stdClass
	 */
	public function getAttributes() {

		return $this->attributes;

	}

	/**
	 * set a single attribute
	 * 
	 * @param string $attr
	 * @param mixed $value
	 */
	public function setAttribute($attr, $value) {

		$this->attributes->$attr = $value;

	}

}

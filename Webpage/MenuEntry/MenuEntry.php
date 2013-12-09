<?php

namespace vxPHP\Webpage\MenuEntry;

use vxPHP\Webpage\Menu\Menu;
use vxPHP\Application\Application;

/**
 * MenuEntry class
 * manages a single menu entry
 *
 * @version 0.3.6 2013-12-08
 */
class MenuEntry {
	protected static	$count = 1;
	protected			$menu,
						$auth,
						$authParameters,
						$attributes,
						$id,
						$page,
						$subMenu,
						$localPage;
	private 			$href;

	public function __construct($page, $attributes, $localPage = TRUE) {
		$this->id			= self::$count++;
		$this->page			= $page;
		$this->localPage	= (boolean) $localPage;
		$this->attributes	= new \StdClass();

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
		return $this->page;
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
		return isset($this->auth) && $privilege >= $this->auth;
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

	public function getPage() {
		return $this->page;
	}

	/**
	 * get href attribute value of menu entry
	 */
	public function getHref() {

		if(is_null($this->href)) {

			if($this->localPage) {

				$pathSegments = array();
				$e = $this;

				do {
					$pathSegments[] = $e->page;
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

				$this->href = $this->page;

			}
		}

		return $this->href;
	}

	public function getAttributes() {
		return $this->attributes;
	}

	public function setAttribute($attr, $value) {
		$this->attributes->$attr = $value;
	}

}

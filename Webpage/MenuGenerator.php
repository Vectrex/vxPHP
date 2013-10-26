<?php

namespace vxPHP\Webpage;

use vxPHP\Http\Request;
use vxPHP\Http\Router;
use vxPHP\User\Admin;
use vxPHP\User\UserAbstract;
use vxPHP\Http\Route;
use vxPHP\Webpage\Exception\MenuGeneratorException;
use vxPHP\Webpage\Menu\MenuInterface;
use vxPHP\Webpage\Menu\Menu;
use vxPHP\Webpage\MenuEntry\MenuEntry;
use vxPHP\Application\Application;
use vxPHP\Application\Config;
use vxPHP\Util\LocalesFactory;

/**
 * Wrapper class for rendering menus
 * custom authentication methods and
 * custom methods for adding menus and menuentries and reworking menus
 * should be implemented in derived classes
 *
 * @author Gregor Kofler
 *
 * @version 0.2.6, 2013-10-22
 *
 * @throws MenuGeneratorException
 */
class MenuGenerator {

	/**
	 * @var boolean
	 */
	protected static $forceActiveMenu;

	/**
	 * @var array
	 * caches already parsed menus
	 */
	protected static $primedMenus = array();

	/**
	 * @var Route
	 */
	protected $route;

	/**
	 * @var Config
	 */
	protected $config;

	/**
	 * @var Request
	 */
	protected $request;

	/**
	 * @var array
	 */
	protected $pathSegments;

	/**
	 * @var Menu
	 */
	protected $menu;

	/**
	 * @var string
	 */
	protected $decorator;

	/**
	 * @var string
	 */
	protected $id;

	/**
	 * @var integer
	 */
	protected $level;

	/**
	 * @var array
	 */
	protected $renderArgs;

	/**
	 * sets active menu entries, allows addition of dynamic entries and
	 * prints level $level of a menu, identified by $id
	 *
	 * $decorator identifies a decorator class - MenuDecorator{$decorator}
	 * $renderArgs are additional parameter passed to Menu::render()
	 *
	 * @param string $id
	 * @param integer $level (if NULL, the full menu tree is printed)
	 * @param boolean $forceActiveMenu
	 * @param string $decorator
	 * @param mixed $renderArgs
	 *
	 * @return string html
	 */
	public function __construct($id = NULL, $level = FALSE, $forceActiveMenu = NULL, $decorator = NULL, $renderArgs = NULL) {

		$this->config	= Application::getInstance()->getConfig();

		if(empty($id) && !is_null($this->config->menus)) {
			throw new MenuGeneratorException();
		}

		$this->request	= Request::createFromGlobals();
		$this->route	= Router::getRouteFromPathInfo();

		if(empty($id)) {
			$id = array_shift(array_keys($this->config->menus));
		}

		if(
			!isset($this->config->menus[$id]) ||
			!count($this->config->menus[$id]->getEntries()) &&
			$this->config->menus[$id]->getType() == 'static'
		) {
			throw new MenuGeneratorException();
		}

		$this->menu = $this->config->menus[$id];

		$this->id				= $id;
		$this->level			= $level;
		$this->decorator		= $decorator;
		$this->renderArgs		= $renderArgs;

		// if $forceActiveMenu was initialized before, it will not be overwritten

		if(is_null(self::$forceActiveMenu)) {
			self::$forceActiveMenu	= (boolean) $forceActiveMenu;
		}

	}

	/**
	 * convenience method to allow chaining
	 * @see __construct()
	 */
	public static function create($id = NULL, $level = FALSE, $forceActiveMenu = NULL, $decorator = NULL, $renderArgs = NULL) {

		return new static($id, $level, $forceActiveMenu, $decorator, $renderArgs);

	}

	/**
	 * activates or deactivates
	 * "active" menu entries (selected entries are clickable)
	 *
	 * @param boolean $state
	 */

	public static function setForceActiveMenu($state) {

		self::$forceActiveMenu = (boolean) $state;

	}

	/**
	 * render menu markup
	 *
	 * @return string
	 */
	public function render() {

		// check authentication

		if(!$this->authenticateMenu($this->menu)) {
			return '';
		}

		// if menu has not been prepared yet, do it now (caching avoids re-parsing for submenus)

		if(!in_array($this->menu, self::$primedMenus, TRUE)) {

			// clear selected menu entries (which remain in the session)

			$this->clearSelectedMenuEntries($this->menu);

			// walk tree, add dynamic menus along the way until an active entry is reached
			// use route id to identify current page in case path segment is empty (e.g. splash page)

			$this->pathSegments = explode('/', trim($this->request->getPathInfo(), '/'));

			// skip script name

			if($this->config->site->use_nice_uris && basename($this->request->getScriptName()) != 'index.php') {
				array_shift($this->pathSegments);
			}

			// skip locale if one found

			if(count($this->pathSegments) && in_array($this->pathSegments[0], LocalesFactory::getAllowedLocales())) {
				array_shift($this->pathSegments);
			}

			$this->walkMenuTree($this->menu, $this->pathSegments[0] === '' ? array($this->route->getRouteId()) : $this->pathSegments);

			// cache menu for multiple renderings

			self::$primedMenus[] = $this->menu;
		}

		$css = "{$this->id}menu";

		// drill down to required submenu (if only submenu needs to be rendered)

		$m = $this->menu;

		if($this->level !== FALSE) {

			$css .= '_level_' . $this->level;

			if($this->level > 0) {

				while($this->level-- > 0) {
					$e = $this->menu->getSelectedEntry();
					if(!$e || !$e->getSubMenu()) {
						break;
					}
					$m = $e->getSubMenu();
				}

				if($this->level >= 0) {
					return '';
				}

			}
		}

		// output

		// instantiate optional decorator class

		if(!empty($this->decorator)) {
			try {
				$className = __NAMESPACE__ . '\\Menu\\Decorator\\MenuDecorator' . $this->decorator;
				$m = new $className($m);
			}
			catch(\Exception $e) {
			}
		}

		return sprintf('<div id="%s">%s</div>', $css, $m->render($this->level === FALSE, self::$forceActiveMenu, $this->renderArgs));

	}

	/**
	 * walk menu $m recursively until path segments are no longer matching, or menu tree ends
	 * if necessary dynamic menus will be added
	 *
	 * @param Menu $m
	 * @param array $pathSegments
	 *
	 * @return MenuEntry or void
	 *
	 */
	private function walkMenuTree(Menu $m, array $pathSegments) {

		if(!count($pathSegments)) {
			return;
		}

		// get current page id to evaluate active menu entry

		$idToFind = array_shift($pathSegments);

		// return when matching entry in current menu

		if(($e = $m->getSelectedEntry())) {
			return $e;
		}

		// if current (sub-)menu is dynamic - generate it and return selected entry

		if($m->getType() == 'dynamic') {

			$method = $m->getMethod();
			if(!$method) {
				$method = 'buildDynamicMenu';
			}

			if(method_exists($this, $method) && ($addedMenu = $this->$method($m, $pathSegments))) {
				return $addedMenu->getSelectedEntry();
			}
		}

		foreach($m->getEntries() as $e) {

			// path segment doesn't match menu entry - finish walk

			if($e->getPage() === $idToFind) {

				$e->getMenu()->setSelectedEntry($e);
				$sm = $e->getSubMenu();

				// if submenu is flagged dynamic: try to generate it

				if($sm && $sm->getType() == 'dynamic') {

					// use either a specified method for building the menu or page::buildDynamicMenu()

					$method = $sm->getMethod();

					if(!$method) {
						$method = 'buildDynamicMenu';
					}

					if(method_exists($this, $method)) {
						$this->$method($sm, $pathSegments);
					}
				}

				// otherwise postprocess menu: insert dynamic menu entries, modify entries, etc.

				else if ($sm && method_exists($this, 'reworkMenu')) {
					$this->reworkMenu($sm);
				}

				// walk  into submenu

				if($sm) {
					$this->walkMenuTree($sm, $pathSegments);
				}
			}
		}
	}

	/**
	 * walks menu tree and clears all previously selected entries
	 *
	 * @param Menu $menu
	 */
	private function clearSelectedMenuEntries(Menu $menu) {

		while(($e = $menu->getSelectedEntry())) {

			// dynamic menus come either with unselected entry or have a selected entry explicitly set

			if($menu->getType() == 'static') {
				$menu->clearSelectedEntry();
			}
			if(!($menu = $e->getSubMenu())) {
				break;
			}
		}
	}

	/**
	 * authenticate complete menu
	 * checks whether current user/admin fulfills requirements defined in site.ini.xml
	 *
	 * if a menu needs authentication and admin meets the required authentication level the menu entries are checked
	 * if single entries require a higher authentication level, they are hidden by setting their display-property to "none"
	 *
	 * authenticateMenuByTableRowAccess(Menu $m)
	 * authenticateMenuByMiscRules(Menu $m)
	 * should be implemented on a per-application level
	 *
	 * @param Menu $m
	 * @return boolean
	 */
	protected function authenticateMenu(MenuInterface $m) {

		if(is_null($m->getAuth())) {
			return TRUE;
		}

		$admin = Admin::getInstance();

		if(!$admin->isAuthenticated()) {
			return FALSE;
		}

		// unhide all menu entries

		foreach($m->getEntries() as $e) {
			$e->setAttribute('display', NULL);
		}

		if($m->getAuth() === UserAbstract::AUTH_OBSERVE_TABLE && $admin->getPrivilegeLevel() >= UserAbstract::AUTH_OBSERVE_TABLE) {
			if($this->authenticateMenuByTableRowAccess($m)) {
				foreach($m->getEntries() as $e) {
					if(!$this->authenticateMenuEntry($e)) {
						$e->setAttribute('display', 'none');
					}
				}
				return TRUE;
			}
			else {
				return FALSE;
			}
		}

		if($m->getAuth() === UserAbstract::AUTH_OBSERVE_ROW && $admin->getPrivilegeLevel() >= UserAbstract::AUTH_OBSERVE_ROW) {
			if($this->authenticateMenuByTableRowAccess($m)) {
				foreach($m->getEntries() as $e) {
					if(!$this->authenticateMenuEntry($e)) {
						$e->setAttribute('display', 'none');
					}
				}
				return TRUE;
			}
			else {
				return FALSE;
			}
		}

		if($m->getAuth() >= $admin->getPrivilegeLevel()) {
			return TRUE;
		}

		return $this->authenticateMenuByMiscRules($m);
	}

	/**
	 * fallback method for authenticating menu access on observe_table/observe_row level
	 * positive authentication if auth_parameter contains a table name found in the admins table access setting
	 *
	 * @param Menu $m
	 * @return boolean
	 */
	protected function authenticateMenuByTableRowAccess(MenuInterface $m) {

		$p = $m->getAuthParameters();

		if(empty($p)) {
			return FALSE;
		}

		$tables = preg_split('/\s*,\s*/', trim($p));
		$admin = Admin::getInstance();

		$matching = array_intersect($tables, $admin->getTableAccess());
		return !empty($matching);
	}

	/**
	 * fallback method for a proprietary authentication method
	 *
	 * @param Menu $m
	 * @return boolean
	 */
	protected function authenticateMenuByMiscRules(MenuInterface $m) {
		return FALSE;
	}

	/**
	 * fallback method for authenticating single menu entry access on observe_table/observe_row level
	 * positive authentication if auth_parameter contains a table name found in the admins table access setting
	 *
	 * @param MenuEntry $e
	 * @return boolean
	 */
	protected function authenticateMenuEntry(MenuEntry $e) {

		$p = $e->getAuthParameters();

		if(empty($p)) {
			return FALSE;
		}

		$tables = preg_split('/\s*,\s*/', trim($p));
		$admin = Admin::getInstance();

		return !array_intersect($tables, $admin->getTableAccess());

	}
}

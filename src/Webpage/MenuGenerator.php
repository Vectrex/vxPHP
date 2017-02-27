<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Webpage;

use vxPHP\Http\Request;
use vxPHP\Routing\Router;
use vxPHP\Routing\Route;
use vxPHP\Webpage\Exception\MenuGeneratorException;
use vxPHP\Webpage\Menu\Menu;
use vxPHP\Webpage\MenuEntry\MenuEntry;
use vxPHP\Application\Application;
use vxPHP\Application\Config;

/**
 * Wrapper class for rendering a menu
 *
 * @author Gregor Kofler
 *
 * @version 0.6.0, 2017-02-16
 *
 * @throws MenuGeneratorException
 */
class MenuGenerator {

	/**
	 * flag indicating that currently selected menu entries still stay
	 * "active", i.e. still render as anchor elements which can be
	 * clicked
	 * 
	 * @var boolean
	 */
	protected static $forceActiveMenu;

	/**
	 * cache holding already parsed menus in case they are rendered
	 * more than once
	 * 
	 * @var Menu[]
	 */
	protected static $primedMenus = [];

	/**
	 * @var Route
	 */
	protected $route;

	/**
	 * indicates whether mod-rewrite like URLs are to be used
	 * 
	 * @var boolean
	 */
	protected $useNiceUris;

	/**
	 * path segments matched against menu structure to evaluate the
	 * active menu entry
	 * 
	 * @var array
	 */
	protected $pathSegments;

	/**
	 * the generated menu instance
	 * 
	 * @var Menu
	 */
	protected $menu;

	/**
	 * class name of decorator to be used
	 * 
	 * @var string
	 */
	protected $decorator;

	/**
	 * unique id of menu
	 * 
	 * @var string
	 */
	protected $id;

	/**
	 * level of menu in menu hierarchy
	 * 
	 * @var integer
	 */
	protected $level;

	/**
	 * additional parameters passed to menu renderer
	 * 
	 * @var array
	 */
	protected $renderArgs;

	/**
	 * class used for menu and menu entry authentication
	 *
	 * @var MenuAuthenticatorInterface
	 */
	protected static $authenticator;
	
	/**
	 * sets active menu entries, allows addition of dynamic entries and
	 * prints level $level of a menu, identified by $id
	 *
	 * $decorator identifies a decorator class - MenuDecorator{$decorator}
	 * $renderArgs are additional parameters passed to Menu::render()
	 *
	 * @param string $id
	 * @param integer $level (if NULL, the full menu tree is printed)
	 * @param boolean $forceActiveMenu
	 * @param string $decorator
	 * @param mixed $renderArgs
	 *
	 * @return string html markup
	 */
	public function __construct($id = NULL, $level = FALSE, $forceActiveMenu = NULL, $decorator = NULL, $renderArgs = NULL) {

		$application = Application::getInstance();

		$config = $application->getConfig();
		$this->useNiceUris = $application->hasNiceUris();

		if(empty($id) && !is_null($config->menus)) {
			throw new MenuGeneratorException();
		}

		$this->route	= $application->getCurrentRoute();

		if(is_null($this->route)) {
			$this->route = Router::getRouteFromPathInfo();
		}

		if(empty($id)) {
			$id = array_shift(array_keys($config->menus));
		}

		if(
			!isset($config->menus[$id]) ||
			!count($config->menus[$id]->getEntries()) &&
			$config->menus[$id]->getType() == 'static'
		) {
			throw new MenuGeneratorException("Menu '" .$id. "' not found or empty.");
		}

		$this->menu = $config->menus[$id];

		$this->id			= $id;
		$this->level		= $level;
		$this->decorator	= $decorator;
		$this->renderArgs	= is_null($renderArgs) ? [] : $renderArgs;

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
	 * set the authenticator class which will be used to authenticate
	 * the menu and menu entries
	 * 
	 * @param MenuAuthenticatorInterface $authenticator
	 */
	public static function setMenuAuthenticator(MenuAuthenticatorInterface $authenticator) {

		self::$authenticator = $authenticator;

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

		$request = Request::createFromGlobals();
		
		// if menu has not been prepared yet, do it now (caching avoids re-parsing for submenus)

		if(!in_array($this->menu, self::$primedMenus, TRUE)) {

			// clear selected menu entries (which remain in the session)

			$this->clearSelectedMenuEntries($this->menu);

			// walk entire menu and add dynamic entries where necessary
			
			$this->completeMenu($this->menu);
			
			// prepare path segments to identify active menu entries

			$this->pathSegments = explode('/', trim($request->getPathInfo(), '/'));

			// skip script name

			if($this->useNiceUris && basename($request->getScriptName()) != 'index.php') {
				array_shift($this->pathSegments);
			}

			// skip locale if one found

			if(count($this->pathSegments) && Application::getInstance()->hasLocale($this->pathSegments[0])) {
				array_shift($this->pathSegments);
			}

			// walk tree until an active entry is reached

			$this->walkMenuTree($this->menu, $this->pathSegments[0] === '' ? explode('/', $this->route->getPath()) : $this->pathSegments);

			// cache menu for multiple renderings

			self::$primedMenus[] = $this->menu;
		}

		$htmlId = $this->id . 'menu';

		// drill down to required submenu (if only submenu needs to be rendered)

		$m = $this->menu;

		if($this->level !== FALSE) {

			$htmlId .= '_level_' . $this->level;

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

		// instantiate renderer class, defaults to SimpleListRenderer

		if(!empty($this->decorator)) {
			$rendererName = $this->decorator;
		}
		else {
			$rendererName = 'SimpleList';
		}

		$className = __NAMESPACE__ . '\\Menu\\Renderer\\' . $rendererName . 'Renderer';

		$renderer = new $className($m);
		$renderer->setParameters($this->renderArgs);

		// enable or disable display of submenus

		$m->setShowSubmenus($this->level === FALSE);

		// enable or disable always active menu

		$m->setForceActive(self::$forceActiveMenu);

		// if no container tag was specified, use a DIV element

		if(!isset($this->renderArgs['containerTag'])) {
			$this->renderArgs['containerTag'] = 'div';
		}

		// omit wrapper, if a falsy container tag was specified

		if($this->renderArgs['containerTag']) {
			return sprintf(
				'<%1$s%2$s>%3$s</%1$s>',
				$this->renderArgs['containerTag'],
				(isset($this->renderArgs['omitId']) && $this->renderArgs['omitId']) ? '' : (' id="' . $htmlId . '"'),
				$renderer->render()
			);
		}

		return $renderer->render();

	}

	
	/**
	 * walk the menu tree
	 * and invoke service to append dynamic menu entries
	 * 
	 * @param Menu $m
	 */
	protected function completeMenu(Menu $m) {
		
		if($m->getType() === 'dynamic') {

			// invoke service to build menu entries
			
			Application::getInstance()->getService($m->getServiceId())->appendMenuEntries($m);

			
		}

		foreach($m->getEntries() as $entry) {
			if(($m = $entry->getSubMenu())) {
				$this->completeMenu($m);
			}
		}

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
	protected function walkMenuTree(Menu $m, array $pathSegments) {

		if(!count($pathSegments)) {
			return;
		}

		// return when matching entry in current menu

		if(($e = $m->getSelectedEntry())) {
			return $e;
		}

		// get current page id to evaluate active menu entry
		
		$pathToMatch = implode('/', $pathSegments);
		
		foreach($m->getEntries() as $e) {

			// path segment doesn't match menu entry - finish walk

			if(0 === strpos($pathToMatch, $e->getPath())) {

				$pathSegments = explode('/', trim(substr($pathToMatch, strlen($e->getPath())), '/'));
				
				$e->getMenu()->setSelectedEntry($e);
				$sm = $e->getSubMenu();

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
	protected function clearSelectedMenuEntries(Menu $menu) {

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
	 * authenticates the complete menu
	 * invokes a previously set authenticator class or falls back
	 * to a default menu authenticator
	 *
	 * @param Menu $menu
	 * @return boolean
	 */
	protected function authenticateMenu(Menu $menu) {

		if(!self::$authenticator) {
				
			self::$authenticator = new DefaultMenuAuthenticator();
		
		}
		
		return self::$authenticator->authenticate($menu, Application::getInstance()->getCurrentUser());

	}

}

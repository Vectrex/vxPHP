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

use vxPHP\Application\Exception\ApplicationException;
use vxPHP\Http\Request;
use vxPHP\Webpage\Exception\MenuGeneratorException;
use vxPHP\Webpage\Menu\Menu;
use vxPHP\Webpage\Menu\Renderer\MenuRendererInterface;
use vxPHP\Webpage\MenuEntry\MenuEntry;
use vxPHP\Application\Application;

/**
 * Wrapper class for rendering a menu
 *
 * @author Gregor Kofler
 *
 * @version 1.2.1, 2021-11-28
 *
 * @throws MenuGeneratorException
 */
class MenuGenerator
{
	/**
	 * flag indicating that currently selected menu entries still stay
	 * "active", i.e. still render as anchor elements which can be
	 * clicked
	 * 
	 * @var ?boolean
	 */
	protected static ?bool $forceActiveMenu = null;

	/**
	 * cache holding already parsed menus in case they are rendered
	 * more than once
	 * 
	 * @var Menu[]
	 */
	protected static array $primedMenus = [];

	/**
	 * path segments matched against menu structure to evaluate the
	 * active menu entry
	 * 
	 * @var array
	 */
	protected array $pathSegments;

	/**
	 * the generated menu instance
	 * 
	 * @var Menu
	 */
	protected Menu $menu;

	/**
	 * class name of decorator to be used
	 * 
	 * @var ?string
	 */
	protected ?string $decorator;

	/**
	 * level of menu in menu hierarchy
	 * 
	 * @var ?integer
	 */
	protected ?int $level;

	/**
	 * additional parameters passed to menu renderer
	 * 
	 * @var ?array
	 */
	protected ?array $renderArgs;

	/**
	 * class used for menu and menu entry authentication
	 *
	 * @var ?MenuAuthenticatorInterface
	 */
	protected static ?MenuAuthenticatorInterface $authenticator = null;

    /**
     * sets active menu entries, allows addition of dynamic entries and
     * prints level $level of a menu
     *
     * the menu can either be passed directly or as an id which must correspond
     * with a configured menu
     *
     * $decorator identifies a decorator class - MenuDecorator{$decorator}
     * $renderArgs are additional parameters passed to Menu::render()
     *
     * @param Menu|string|null $menuOrId (if NULL the first configured menu is used)
     * @param int|null $level (if NULL, a full menu tree is rendered)
     * @param bool $forceActiveMenu
     * @param string|null $decorator
     * @param mixed $renderArgs
     *
     * @throws ApplicationException
     * @throws MenuGeneratorException
     */
	public function __construct(Menu|string|null $menuOrId = null, ?int $level = null, bool $forceActiveMenu = false, ?string $decorator = null, mixed $renderArgs = null)
    {
        if ($menuOrId instanceof Menu) {
            $this->menu = $menuOrId;
        }
        else {
            $config = Application::getInstance()->getConfig();

            if (empty($menuOrId)) {

                // fall back to menu with default id or first menu in configured list

                $menuOrId = array_key_exists(Menu::DEFAULT_ID, $config->menus) ? Menu::DEFAULT_ID : (array_keys($config->menus)[0] ?? null);
            }
            if (
                !isset($config->menus[$menuOrId]) ||
                (
                    !count($config->menus[$menuOrId]->getEntries()) &&
                    $config->menus[$menuOrId]->getType() === 'static'
                )
            ) {
                throw new MenuGeneratorException(sprintf("Menu '%s' not found or empty.", $menuOrId));
            }
            $this->menu = $config->menus[$menuOrId];
        }

        $this->pathSegments = $this->primePathSegments();
		$this->level = $level;
		$this->decorator = $decorator;
		$this->renderArgs = $renderArgs ?? [];

		// if $forceActiveMenu was initialized before, it will not be overwritten

		if(is_null(self::$forceActiveMenu)) {
			self::$forceActiveMenu = $forceActiveMenu;
		}
	}

    /**
     * convenience method to allow chaining
     *
     * @param Menu|string|null $menuOrId (if NULL the first configured menu is used)
     * @param int|null $level (if NULL, the full menu tree is printed)
     * @param bool $forceActiveMenu
     * @param string|null $decorator
     * @param mixed $renderArgs
     * @return MenuGenerator
     * @throws ApplicationException
     * @throws MenuGeneratorException
     */
	public static function create(Menu|string|null $menuOrId = null, ?int $level = null, bool $forceActiveMenu = false, ?string $decorator = null, mixed $renderArgs = null): MenuGenerator
    {
		return new static(...func_get_args());
	}

    private function primePathSegments (): array
    {
        $application = Application::getInstance();
        $router = $application->getRouter();
        $request = Request::createFromGlobals();

        $rewriteActive = $router && $router->getServerSideRewrite();

        $route = $application->getCurrentRoute() ?? ($router?->getRouteFromPathInfo($request));

        $routePath = $route ? $route->getPath() : '';

        // prepare path segments to identify active menu entries

        $pathSegments = explode('/', trim($request->getPathInfo(), '/'));

        // skip script name

        if($rewriteActive && basename($request->getScriptName()) !== 'index.php') {
            array_shift($pathSegments);
        }

        // skip locale if one found

        if(count($pathSegments) && $application->hasLocale($pathSegments[0])) {
            array_shift($pathSegments);
        }

        // if pathSegments are empty use route path as fallback

        if (!count($pathSegments) || $pathSegments[0] === '') {
            return explode('/', $routePath);
        }

        return $pathSegments;
    }

	/**
	 * activates or deactivates
	 * "active" menu entries (selected entries are clickable)
	 *
	 * @param boolean $state
	 */

	public static function setForceActiveMenu(bool $state): void
    {
		self::$forceActiveMenu = $state;
	}
	
	/**
	 * set the authenticator class which will be used to authenticate
	 * the menu and menu entries
	 * 
	 * @param MenuAuthenticatorInterface $authenticator
	 */
	public static function setMenuAuthenticator(MenuAuthenticatorInterface $authenticator): void
    {
		self::$authenticator = $authenticator;
	}

    /**
     * render menu markup
     *
     * @return string
     * @throws ApplicationException
     */
	public function render(): string
    {
		// check authentication

		if(!$this->authenticateMenu($this->menu)) {
			return '';
		}

		// if menu has not been prepared yet, do it now (caching avoids reparsing for submenus)

		if(!in_array($this->menu, self::$primedMenus, true)) {

			// clear selected menu entries (which remain in the session)

			$this->clearSelectedMenuEntries($this->menu);

			// walk entire menu and add dynamic entries where necessary
			
			$this->completeMenu($this->menu);
			
			// walk tree until an active entry is reached

			$this->walkMenuTree($this->menu, $this->pathSegments);

			// cache menu for multiple renderings

			self::$primedMenus[] = $this->menu;
		}

		// drill down to required submenu (if only submenu needs to be rendered)

		$m = $this->menu;

		if ($this->level > 0) {

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

		// output

		// instantiate renderer class, defaults to SimpleListRenderer

		if(!empty($this->decorator)) {
			$rendererName = $this->decorator;
		}
		else {
			$rendererName = 'SimpleList';
		}

		$className = __NAMESPACE__ . '\\Menu\\Renderer\\' . $rendererName . 'Renderer';

        /* @var MenuRendererInterface $renderer */

		$renderer = new $className($m);
		$renderer->setParameters($this->renderArgs);

        if ($m) {

            // enable or disable display of submenus

            $m->setShowSubmenus($this->level === null);

            // enable or disable always active menu

            $m->setForceActive((bool) self::$forceActiveMenu);
        }

		return $renderer->render();
	}

    /**
     * walk the menu tree
     * and invoke service to append dynamic menu entries
     *
     * @param Menu $m
     * @throws ApplicationException
     */
	protected function completeMenu(Menu $m): void
    {
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
	 * @return ?MenuEntry
	 *
	 */
	protected function walkMenuTree(Menu $m, array $pathSegments): ?MenuEntry
    {
		if(!count($pathSegments)) {
			return null;
		}

		// return when matching entry in current menu

		if(($e = $m->getSelectedEntry())) {
			return $e;
		}

        // get current page id to evaluate active menu entry
		
		$pathToMatch = urldecode(implode('/', $pathSegments));
		
		foreach($m->getEntries() as $e) {

			$path = urldecode($e->getPath());

            // check for a possible "root" (i.e. "/") path

            if(!$path && !$pathToMatch) {
                $e->getMenu()?->setSelectedEntry($e);
                return $e;
            }

            // path segment doesn't match menu entry - finish walk

			if($path && str_starts_with($pathToMatch, $path)) {

				$pathSegments = explode('/', trim(substr($pathToMatch, strlen($path)), '/'));

                $e->getMenu()?->setSelectedEntry($e);
				$sm = $e->getSubMenu();

				// walk  into submenu

				if($sm) {
					$this->walkMenuTree($sm, $pathSegments);
				}
			}
		}

		return null;
	}

	/**
	 * walks menu tree and clears all previously selected entries
	 *
	 * @param Menu $menu
	 */
	protected function clearSelectedMenuEntries(Menu $menu): void
    {
		while(($e = $menu->getSelectedEntry())) {

			// dynamic menus come either with unselected entry or have a selected entry explicitly set

			if($menu->getType() === 'static') {
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
     * @throws ApplicationException
     */
	protected function authenticateMenu(Menu $menu): bool
    {
		if(!self::$authenticator) {
			self::$authenticator = new DefaultMenuAuthenticator();
		}
		return self::$authenticator->authenticate($menu, Application::getInstance()->getCurrentUser());
	}
}

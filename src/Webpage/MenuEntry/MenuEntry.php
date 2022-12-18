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
use vxPHP\User\Role;

/**
 * MenuEntry class
 * manages a single menu entry
 *
 * @version 0.8.3 2021-12-06
 */
class MenuEntry
{
	/**
	 * the index counter of menu entries
	 * @var integer
	 */
	protected static int $count = 1;

	/**
	 * the menu the entry belongs to
	 * @var Menu | null
	 */
	protected ?Menu $menu;

	/**
	 * the authentication level of the entry
	 * @var string | null
	 */
	protected ?string $auth = null;
	
	/**
	 * additional authentication parameter which
	 * might be required by the authentication level
	 * @var string
	 */
	protected string $authParameters;
	
	/**
	 * misc attributes
	 * @var \stdClass
	 */
	protected \stdClass $attributes;

    /**
     * display attribute
     * @var boolean
     */
    protected bool $display = true;

	/**
	 * unique index of menu entry
	 * @var integer
	 */
	protected int $ndx;
	
	/**
	 * the path of the menu entry 
	 * @var string
	 */
	protected string $path;
	
	/**
	 * an optional submenu of the menu entry
	 * @var Menu | null
	 */
	protected ?Menu $subMenu = null;
	
	/**
	 * flag indicating whether menu entry destination is an absoulte or relative URL
	 * @var boolean
	 */
	protected bool $localPage;
	
	/**
	 * the URL of the menu entry
	 * @var string | null
	 */
	protected ?string $href;

    /**
     * MenuEntry constructor.
     *
     * @param string $path
     * @param array $attributes
     * @param bool $localPage
     */
	public function __construct(string $path, array $attributes = [], bool $localPage = true)
    {
		$this->ndx = self::$count++;
		$this->setPath($path);
		$this->localPage = $localPage;
		$this->attributes = new \stdClass();

		foreach($attributes as $attr => $value) {
			$attr = strtolower($attr);
			$this->attributes->$attr = $value;
		}
	}

	// purge dynamically generated submenus

	public function __destruct()
    {
		if($this->subMenu && $this->subMenu->getType() === 'dynamic') {
			$this->subMenu->__destruct();
		}
	}

	public function __toString(): string
    {
		return $this->path;
	}

    /**
     * append menu to menu entry
     *
     * @param Menu $menu
     * @return MenuEntry
     */
    public function appendMenu(Menu $menu): MenuEntry
    {
		$menu->setParentEntry($this);
		$this->subMenu = $menu;
        return $this;
	}

    /**
     * assign this menu entry to a menu
     *
     * @param Menu $menu
     * @return $this
     */
    public function setMenu(Menu $menu): MenuEntry
    {
		$this->menu = $menu;
		return $this;
	}

    /**
     * get menu the entry belongs to
     *
     * @return Menu
     */
	public function getMenu(): ?Menu
    {
		return $this->menu;
	}

    /**
     * get auth information
     *
     * @return string
     */
	public function getAuth(): ?string
    {
		return $this->auth;
	}

    /**
     * set auth information
     *
     * @param string $auth
     * @return $this
     */
	public function setAuth(string $auth): MenuEntry
    {
		$this->auth = $auth;
		return $this;
	}

    /**
     * get additonal auth parameters
     *
     * @return string
     */
	public function getAuthParameters(): ?string
    {
		return $this->authParameters;
	}

    /**
     * set additional auth parameters
     *
     * @param string $authParameters
     * @return $this
     */
	public function setAuthParameters(string $authParameters): MenuEntry
    {
		$this->authParameters = $authParameters;
		return $this;
	}

	/**
	 * check whether a role matches the auth attribute of the menu entry
	 * 
	 * @param Role $role
	 * @return boolean
	 */
	public function isAuthenticatedByRole(Role $role): bool
    {
		return !isset($this->auth) || $this->auth === $role->getRoleName();
	}

    /**
     * check whether menu entry points to a local page and not an external URL
     *
     * @return bool
     */
	public function refersLocalPage(): bool
    {
		return $this->localPage;
	}

    /**
     * mark the menu entry as selected
     *
     * @return $this
     */
	public function select(): MenuEntry
    {
		$this->menu->setSelectedEntry($this);
		return $this;
	}

    /**
     * get sub menu
     *
     * @return Menu
     */
	public function getSubMenu(): ?Menu
    {
		return $this->subMenu;
	}

    /**
     * get path configured with route
     *
     * @return string
     */
	public function getPath(): string
    {
		return $this->path;
	}

    /**
     * @param string $path
     * @return MenuEntry
     */
    public function setPath(string $path): self
    {
        $this->path = trim($path, '/');

        // ensure href (re-)evaluation

        $this->href = null;
        return $this;
    }

    /**
	 * get href attribute value of menu entry
	 */
	public function getHref(): string
    {
		if(is_null($this->href)) {

			if($this->localPage) {

                $router = Application::getInstance()->getRouter();

                if(!$router) {
                    throw new \RuntimeException('Not router assigned. Cannot create href attribute for menu entry.');
                }

                $pathSegments = [];
				$e = $this;

				do {
                    array_unshift($pathSegments, ...explode('/', $e->path));
				} while ($e = $e->menu->getParentEntry());

                if($router->getServerSideRewrite()) {
					if(($script = basename($this->menu->getScript(), '.php')) === 'index') {
						$script = '/';
					}
					else {
						$script = '/'. $script . '/';
					}
				} else if ($relPath = $router->getRelativeAssetsPath()) {
                    $script = '/' . trim($relPath, '/') . '/' . $this->menu->getScript() . '/';
                } else {
                    $script = '/' . $this->menu->getScript() . '/';
                }

                $this->href = $script . implode('/', array_map('rawurlencode', $pathSegments));

			}
			else {
				$this->href = $this->path;
			}
		}

		return $this->href;
	}

    /**
     * @return bool
     */
    public function getDisplay(): bool
    {
        return $this->display;
    }

    /**
     * @param bool $display
     * @return MenuEntry
     */
    public function setDisplay(bool $display): MenuEntry
    {
        $this->display = $display;
        return $this;
    }

    /**
     * get a single attribute
     *
     * @param string $attr
     * @param mixed $default
     * @return mixed
     */
    public function getAttribute(string $attr, $default = null)
    {
        $attr = strtolower($attr);
        return $this->attributes->$attr ?? $default;
    }

    /**
     * set a single attribute
     *
     * @param string $attr
     * @param mixed $value
     * @return MenuEntry
     */
	public function setAttribute(string $attr, $value): MenuEntry
    {
        $attr = strtolower($attr);
		$this->attributes->$attr = $value;
		return $this;
	}
}

<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Webpage\Menu;

use vxPHP\Webpage\MenuEntry\MenuEntry;
use vxPHP\Webpage\MenuEntry\DynamicMenuEntry;

/**
 * Menu class
 *
 * manages a complete menu
 * 
 * @author Gregor Kofler
 * @version 1.1.1 2021-11-28
 */
class Menu
{
    /**
     * the id of a menu which has no id explicitly set
     */
    public const DEFAULT_ID = '__default__';

    /**
     * @var string
     */
    protected string $script;

    /**
     * @var string
     */
    protected string $type;

    /**
	 * @var string|null
     */
	protected ?string $id;

	/**
	 * @var string|null
     */
	protected ?string $serviceId;

	/**
	 * @var string|null
     */
	protected ?string $auth = null;
	
	/**
	 * @var string|null
     */
	protected ?string $authParameters = null;
	
	/**
	 * @var MenuEntry[]
	 */
	protected array $entries = [];
	
	/**
	 * @var DynamicMenuEntry[]
	 */
	protected array $dynamicEntries = [];

	/**
	 * @var MenuEntry|null
     */
	protected ?MenuEntry $selectedEntry = null;

	/**
	 * @var MenuEntry|null
     */
	protected ?MenuEntry $parentEntry = null;
	
	/**
	 * @var boolean
	 */
	protected bool $forceActive = false;

	/**
	 * @var boolean
	 */
	protected bool $showSubmenus = false;

    /**
     * @var boolean
     */
    protected bool $display = true;

    /**
     * misc attributes
     * @var \stdClass
     */
    protected \stdClass $attributes;

    public function __construct(string $script, string $id = null, string $type = 'static', string $serviceId = null)
    {
		$this->script = $script;
		$this->id = $id;
		$this->type = $type;
		$this->serviceId = $serviceId;
		$this->attributes = new \stdClass();
	}

	public function __destruct()
    {
		if($this->type === 'dynamic') {
			$this->clearSelectedEntry();
			$this->purgeEntries();
		}
		else {

			foreach($this->entries as $k => $e) {
				if($e instanceOf DynamicMenuEntry) {
					array_splice($this->entries, $k, 1);
					$e = null;
				}
			}

			$this->dynamicEntries = [];
		}
	}

    /**
     * insert or append menu entry
     *
     * @param MenuEntry $entry
     * @param int | null $ndx
     * @return Menu
     */
	protected function insertEntry(MenuEntry $entry, int $ndx = null): Menu
    {
		if($entry instanceof DynamicMenuEntry) {
			$this->dynamicEntries[] = $entry;
		}

		if(!isset($ndx) || $ndx >= count($this->entries)) {
			$this->entries[] = $entry;
		}
		else {
			array_splice($this->entries, $ndx, 0, [$entry]);
		}

		$entry->setMenu($this);
		
		return $this;
	}

	/**
	 * insert or append several MenuEntry objects
	 *
	 * @param array $entries
	 * @param int|null $ndx
	 * @return Menu
	 */
	public function insertEntries(array $entries, int $ndx = null): Menu
    {
        if (!isset($ndx)) {
            foreach($entries as $e) {
                $this->insertEntry($e);
            }
        }
        else {
            foreach ($entries as $e) {
                $this->insertEntry($e, $ndx++);
            }
        }

		return $this;
	}

	/**
	 * append a single menu entry
	 *
	 * @param MenuEntry $entry
 	 * @return Menu
	 */
	public function appendEntry(MenuEntry $entry): Menu
    {
		$this->insertEntry($entry);
		return $this;
	}

	/**
	 * append a several menu entry
	 *
	 * @param array $entries
 	 * @return Menu
	 */
	public function appendEntries(array $entries): Menu
    {
		foreach($entries as $entry) {
			$this->insertEntry($entry);
		}

		return $this;
	}

	/**
	 * insert a MenuEntry before an existing MenuEntry
	 *
	 * @param MenuEntry $new
	 * @param MenuEntry $pos
 	 * @return Menu
	 */
	public function insertBeforeEntry(MenuEntry $new, MenuEntry $pos): Menu
    {
		foreach($this->entries as $k => $e) {
			if($e === $pos) {
				$this->insertEntry($new, $k);
				break;
			}
		}
		
		return $this;
	}

	/**
	 * get MenuEntry at position $ndx
	 *
	 * @param int $ndx
	 * @return MenuEntry
	 */
	public function getEntryAtPos(int $ndx): MenuEntry
    {
		return $this->entries[$ndx];
	}

	/**
	 * replace a MenuEntry by another one
	 *
	 * @param MenuEntry $new
	 * @param MenuEntry $toReplace
 	 * @return Menu
	 */
	public function replaceEntry(MenuEntry $new, MenuEntry $toReplace): Menu
    {
		foreach($this->entries as $k => $e) {
			if($e === $toReplace) {
				$this->insertEntry($new, $k);
				$this->removeEntry($toReplace);
				break;
			}
		}
		
		return $this;
	}

	/**
	 * remove a MenuEntry
	 *
	 * @param MenuEntry $toRemove
 	 * @return Menu
	 */
	public function removeEntry(MenuEntry $toRemove): Menu
    {
		foreach($this->entries as $k => $e) {

			if($e === $toRemove) {

				array_splice($this->entries, $k, 1);

				if($toRemove instanceof DynamicMenuEntry) {

					$ndx = array_search($toRemove, $this->dynamicEntries, true);

					if($ndx !== false) {
						array_splice($this->dynamicEntries, $ndx, 1);
					}

				}
				break;
			}
		}
		
		return $this;
	}

	/**
	 * remove all static and dynamic entries
	 * 
 	 * @return Menu
	 */
	public function purgeEntries(): Menu
    {
		$this->entries = [];
		$this->dynamicEntries = [];

		return $this;
	}

	/**
	 * get id of menu
	 * 
	 * @return string int
	 */
	public function getId(): string
    {
		return $this->id ?: self::DEFAULT_ID;
	}

	/**
	 * get script associated with menu
	 * 
	 * @return string
	 */
	public function getScript(): string
    {
		return $this->script;
	}

	/**
	 * get type of menu [dynamic|static]
	 * 
	 * @return string type
	 */
	public function getType(): string
    {
		return $this->type;
	}

	/**
	 * get service id if menu is of type dynamic
	 * 
	 * @return string service id
	 */
	public function getServiceId(): ?string
    {
		return $this->serviceId;
	}

	/**
	 * get all entries of menu
	 * 
	 * @return MenuEntry[]
	 */
	public function getEntries(): array
    {
		return $this->entries;
	}

	/**
	 * get parent menu entry this menu is attached to
	 * 
	 * @return MenuEntry
	 */
	public function getParentEntry(): ?MenuEntry
    {
		return $this->parentEntry;
	}

	/**
	 * make menu to submenu of menu entry
	 *
	 * @param MenuEntry $e
	 * @return Menu
	 */
	public function setParentEntry(MenuEntry $e): Menu
    {
		$this->parentEntry = $e;
		return $this;
	}

	/**
	 * get currently selected menu entry
	 * 
	 * @return MenuEntry
	 */
	public function getSelectedEntry(): ?MenuEntry
    {
		return $this->selectedEntry;
	}

	/**
	 * explicitly set MenuEntry as selected
	 *
	 * @param MenuEntry $e
	 * @return Menu
	 */
	public function setSelectedEntry(MenuEntry $e): Menu
    {
		$this->selectedEntry = $e;
		return $this;
	}

	/**
	 * clear a selected entry
	 * 
	 * @return Menu
	 */
	public function clearSelectedEntry(): Menu
    {
		$this->selectedEntry = null;
		return $this;
	}

	/**
	 * get authentication level of menu
	 * 
	 * @return string
	 */
	public function getAuth(): ?string
    {
		return $this->auth;
	}

	/**
	 * set authentication level of menu
	 * 
	 * @param string $auth
	 * @return Menu
	 */
	public function setAuth(string $auth): Menu
    {
		$this->auth = $auth;
		return $this;
	}

	/**
	 * get additional authentication parameters of menu
	 * 
	 * @return string
	 */
	public function getAuthParameters(): ?string
    {
		return $this->authParameters;
	}

	/**
	 * set additional authentication parameters of menu
	 * 
	 * @param string $authParameters
	 * 
	 * @return Menu
	 */
	public function setAuthParameters(string $authParameters): Menu
    {
		$this->authParameters = $authParameters;
		return $this;
	}

	/**
	 * check whether menu passes authentication level of passed privilege
	 * 
	 * @param string $privilege
	 * @return boolean
	 */
	public function isAuthenticatedBy(string $privilege): bool
    {
		return isset($this->auth) && $privilege <= $this->auth;
	}

	/**
	 * check whether the active menu entry is interactive
	 * 
	 * @return boolean
	 */
	public function getForceActive(): bool
    {
		return $this->forceActive;
	}

	/**
	 * force interactive active menu entry
	 * 
	 * @param boolean $state
	 * @return Menu
	 */
	public function setForceActive(bool $state): Menu
    {
		$this->forceActive = $state;
		return $this;
	}

	/**
	 * check whether submenus are shown
	 * 
	 * @return boolean
	 */
	public function getShowSubmenus(): bool
    {
		return $this->showSubmenus;
	}

	/**
	 * enable or disable showing of submenus
	 * 
	 * @param boolean $state
	 * @return Menu
	 * 
	 */
	public function setShowSubmenus(bool $state): Menu
    {
		$this->showSubmenus = $state;
		return $this;
	}

	/**
	 * get all dynamic menu entries of menu
	 * 
	 * @return DynamicMenuEntry[]
	 */
	public function getDynamicEntries(): array
    {
		return $this->dynamicEntries;
	}

    /**
     * get display setting of menu
     *
     * @return bool
     */
    public function getDisplay(): bool
    {
        return $this->display;
    }

    /**
     * set rendering of entire menu
     *
     * @param bool $display
     * @return Menu
     */
    public function setDisplay(bool $display): Menu
    {
        $this->display = $display;
        return $this;
    }

    /**
     * set a single attribute
     *
     * @param string $attr
     * @param mixed $value
     * @return Menu
     */
    public function setAttribute(string $attr, $value): Menu
    {
        $attr = strtolower($attr);
        $this->attributes->$attr = $value;
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
}
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
 * @version 0.9.0 2016-07-26
 */
class Menu {

	/**
	 * @var string
	 */
	protected $id;

	/**
	 * @var string
	 */
	protected $script;

	/**
	 * @var string
	 */
	protected $type;

	/**
	 * @var string
	 */
	protected $serviceId;

	/**
	 * @var string
	 */
	protected $auth;
	
	/**
	 * @var string
	 */
	protected $authParameters;
	
	/**
	 * @var MenuEntry[]
	 */
	protected $entries = [];
	
	/**
	 * @var DynamicMenuEntry[]
	 */
	protected $dynamicEntries = [];

	/**
	 * @var MenuEntry
	 */
	protected $selectedEntry;

	/**
	 * @var MenuEntry
	 */
	protected $parentEntry;
	
	/**
	 * @var boolean
	 */
	protected $forceActive;

	/**
	 * @var boolean
	 */
	protected $showSubmenus;

	public function __construct($script, $id = NULL, $type, $serviceId = NULL) {

		$this->script		= $script;
		$this->id			= $id;
		$this->type			= $type;
		$this->serviceId	= $serviceId;

	}

	public function __destruct() {

		if($this->type === 'dynamic') {
			$this->clearSelectedEntry();
			$this->purgeEntries();
		}

		else {

			foreach($this->entries as $k => $e) {
				if($e instanceOf DynamicMenuEntry) {
					array_splice($this->entries, $k, 1);
					$e = NULL;
				}
			}

			$this->dynamicEntries = [];

		}
	}

	/**
	 * insert or append menu entry
	 *
	 * @param MenuEntry $entry
	 * @param int $ndx
	 * @return Menu
	 */
	protected function insertEntry(MenuEntry $entry, $ndx = NULL) {

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
	 * @param insert $ndx
	 * @return Menu
	 */
	public function insertEntries(array $entries, $ndx) {

		foreach($entries as $e) {
			$this->insertEntry($e, $ndx++);
		}

		return $this;

	}

	/**
	 * append a single menu entry
	 *
	 * @param MenuEntry $entry
 	 * @return Menu
	 */
	public function appendEntry(MenuEntry $entry) {

		$this->insertEntry($entry);

		return $this;
		
	}

	/**
	 * append a several menu entry
	 *
	 * @param array $entries
 	 * @return Menu
	 */
	public function appendEntries(array $entries) {
		
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
	public function insertBeforeEntry(MenuEntry $new, MenuEntry $pos) {

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
	public function getEntryAtPos($ndx) {

		return $this->entries[$ndx];

	}

	/**
	 * replace a MenuEntry by another one
	 *
	 * @param MenuEntry $new
	 * @param MenuEntry $toReplace
 	 * @return Menu
	 */
	public function replaceEntry(MenuEntry $new, MenuEntry $toReplace) {

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
	public function removeEntry(MenuEntry $toRemove) {

		foreach($this->entries as $k => $e) {

			if($e === $toRemove) {

				array_splice($this->entries, $k, 1);

				if($toRemove instanceof DynamicMenuEntry) {

					$ndx = array_search($toRemove, $this->dynamicEntries, TRUE);

					if($ndx !== FALSE) {
						array_splice($this->dynamicEntries, $ndx, 1);
					}

				}

				$toRemove = NULL;
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
	public function purgeEntries() {

		$this->entries = [];
		$this->dynamicEntries = [];

		return $this;
	}

	/**
	 * get id of menu
	 * 
	 * @return string int
	 */
	public function getId() {

		return $this->id;

	}

	/**
	 * get script associated with menu
	 * 
	 * @return string
	 */
	public function getScript() {

		return $this->script;

	}

	/**
	 * get type of menu [dynamic|static]
	 * 
	 * @return string type
	 */
	public function getType() {

		return $this->type;

	}

	/**
	 * get service id if menu is of type dynamic
	 * 
	 * @return string service id
	 */
	public function getServiceId() {
	
		return $this->serviceId;

	}

	/**
	 * get all entries of of menu 
	 * 
	 * @return MenuEntry[]
	 */
	public function getEntries() {

		return $this->entries;

	}

	/**
	 * get parent menu entry this menu is attached to
	 * 
	 * @return MenuEntry
	 */
	public function getParentEntry() {

		return $this->parentEntry;

	}

	/**
	 * make menu to submenu of menu entry
	 *
	 * @param MenuEntry $e
	 * @return Menu
	 */
	public function setParentEntry(MenuEntry $e) {

		$this->parentEntry = $e;
		return $this;

	}

	/**
	 * get currently selected menu entry
	 * 
	 * @return MenuEntry
	 */
	public function getSelectedEntry() {

		return $this->selectedEntry;

	}

	/**
	 * explicitly set MenuEntry as selected
	 *
	 * @param MenuEntry $e
	 * @return Menu
	 */
	public function setSelectedEntry(MenuEntry $e) {

		$this->selectedEntry = $e;
		return $this;

	}

	/**
	 * clear a selected entry
	 * 
	 * @return Menu
	 */
	public function clearSelectedEntry() {

		$this->selectedEntry = NULL;
		return $this;

	}

	/**
	 * get authentication level of menu
	 * 
	 * @return string
	 */
	public function getAuth() {

		return $this->auth;

	}

	/**
	 * set authentication level of menu
	 * 
	 * @param string $auth
	 * @return Menu
	 */
	public function setAuth($auth) {

		$this->auth = $auth;
		return $this;
		
	}

	/**
	 * get additional authentication parameters of menu
	 * 
	 * @return string
	 */
	public function getAuthParameters() {

		return $this->authParameters;

	}

	/**
	 * set additional authentication parameters of menu
	 * 
	 * @param string $authParameters
	 * 
	 * @return Menu
	 */
	public function setAuthParameters($authParameters) {

		$this->authParameters = $authParameters;
		return $this;

	}

	/**
	 * check whether menu passes authentication level of passed privilege
	 * 
	 * @param string $privilege
	 * @return boolean
	 */
	public function isAuthenticatedBy($privilege) {

		return isset($this->auth) && $privilege <= $this->auth;

	}

	/**
	 * check whether the active menu entry is interactive
	 * 
	 * @return boolean
	 */
	public function getForceActive() {

		return !!$this->forceActive;

	}

	/**
	 * force interactive active menu entry
	 * 
	 * @param boolean $state
	 * @return Menu
	 */
	public function setForceActive($state) {

		$this->forceActive = !!$state;
		return $this;

	}

	/**
	 * check whether submenus are shown
	 * 
	 * @return boolean
	 */
	public function getShowSubmenus() {

		return !!$this->showSubmenus;

	}

	/**
	 * enable or disable showing of submenus
	 * 
	 * @param boolean $state
	 * @return Menu
	 * 
	 */
	public function setShowSubmenus($state) {

		$this->showSubmenus = !!$state;
		return $this;

	}

	/**
	 * get all dynamic menu entries of menu
	 * 
	 * @return DynamicMenuEntry[]
	 */
	public function getDynamicEntries() {

		return $this->dynamicEntries;

	}

}
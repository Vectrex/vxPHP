<?php

namespace vxPHP\Webpage\Menu;

use vxPHP\Webpage\Menu\MenuInterface;

use vxPHP\Webpage\MenuEntry\MenuEntry;
use vxPHP\Webpage\MenuEntry\DynamicMenuEntry;

/**
 * Menu class
 *
 * manages a complete menu
 * @version 0.7.1 2013-10-10
 */
class Menu implements MenuInterface {

	protected	$id,
				$script,
				$type,
				$method,
				$auth,
				$authParameters,
				$entries		= array(),
				$dynamicEntries	= array(),

				/**
				 * @var MenuEntry
				 */
				$selectedEntry,

				/**
				 * @var MenuEntry
				 */
				$parentEntry,
				$forceActive,
				$showSubmenus;

	public function __construct($script, $id = NULL, $type, $method = NULL) {
		$this->script	= $script;
		$this->id		= $id;
		$this->type		= $type;
		$this->method	= $method;
	}

	public function __destruct() {

		if($this->type == 'dynamic') {
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

			$this->dynamicEntries = array();

		}
	}

	/**
	 * insert or append menu entry
	 *
	 * @param MenuEntry $entry
	 * @param int $ndx
	 */
	protected function insertEntry(MenuEntry $entry, $ndx = NULL) {

		if($entry instanceof DynamicMenuEntry) {
			$this->dynamicEntries[] = $entry;
		}

		if(!isset($ndx) || $ndx >= count($this->entries)) {
			$this->entries[] = $entry;
		}
		else {
			array_splice($this->entries, $ndx, 0, array($entry));
		}

		$entry->setMenu($this);
	}

	/**
	 * insert or append several MenuEntry objects
	 *
	 * @param array $entries
	 * @param insert $ndx
	 */
	public function insertEntries(Array $entries, $ndx) {
		foreach($this->entries as $e) {
			$this->insertEntry($e, $ndx++);
		}
	}

	/**
	 * append a single menu entry
	 *
	 * @param MenuEntry $entry
	 */
	public function appendEntry(MenuEntry $entry) {
		$this->insertEntry($entry);
	}

	/**
	 * insert a MenuEntry before an existing MenuEntry
	 *
	 * @param MenuEntry $new
	 * @param MenuEntry $pos
	 */
	public function insertBeforeEntry(MenuEntry $new, MenuEntry $pos) {

		foreach($this->entries as $k => $e) {
			if($e === $pos) {
				$this->insertEntry($new, $k);
				break;
			}
		}

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
	 */
	public function replaceEntry(MenuEntry $new, MenuEntry $toReplace) {

		foreach($this->entries as $k => $e) {
			if($e === $toReplace) {
				$this->insertEntry($new, $k);
				$this->removeEntry($toReplace);
				break;
			}
		}

	}

	/**
	 * remove a MenuEntry
	 *
	 * @param MenuEntry $toRemove
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
	}

	/**
	 * remove all static and dynamic entries
	 */
	public function purgeEntries() {
		$this->entries = array();
		$this->dynamicEntries = array();
	}

	/**
	 * @return string int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return string type
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @return string method
	 */
	public function getMethod() {
		return $this->method;
	}

	/**
	 * @return array
	 */
	public function getEntries() {
		return $this->entries;
	}

	/**
	 * @return MenuEntry
	 */
	public function getParentEntry() {
		return $this->parentEntry;
	}

	/**
	 * make menu to submenu of MenuEntry
	 *
	 * @param MenuEntry $e
	 */
	public function setParentEntry(MenuEntry $e) {
		$this->parentEntry = $e;
	}

	/**
	 * @return string
	 */
	public function getScript() {
		return $this->script;
	}

	/**
	 * @return MenuEntry
	 */
	public function getSelectedEntry() {
		return $this->selectedEntry;
	}

	/**
	 * explicitly set MenuEntry as selected
	 *
	 * @param MenuEntry $e
	 */
	public function setSelectedEntry(MenuEntry $e) {
		$this->selectedEntry = $e;
	}

	/**
	 * clear a selected entry
	 */
	public function clearSelectedEntry() {
		$this->selectedEntry = NULL;
	}

	/**
	 * @return string
	 */
	public function getAuth() {
		return $this->auth;
	}

	public function setAuth($auth) {
		$this->auth = $auth;
	}

	/**
	 * @return string
	 */
	public function getAuthParameters() {
		return $this->authParameters;
	}

	/**
	 * @param string $authParameters
	 */
	public function setAuthParameters($authParameters) {
		$this->authParameters = $authParameters;
	}

	/**
	 * @param string $privilege
	 * @return boolean
	 */
	public function isAuthenticatedBy($privilege) {
		return isset($this->auth) && $privilege >= $this->auth;
	}

	/**
	 * @return boolean
	 */
	public function getForceActive() {
		return !!$this->forceActive;
	}

	/**
	 * @param boolean $state
	 */
	public function setForceActive($state) {
		$this->forceActive = !!$state;
	}

	/**
	 * @return boolean
	 */
	public function getShowSubmenus() {
		return !!$this->showSubmenus;
	}

	/**
	 * @param boolean $state
	 */
	public function setShowSubmenus($state) {
		$this->showSubmenus = !!$state;
	}

	/**
	 * @return array
	 */
	public function getDynamicEntries() {
		return $this->dynamicEntries;
	}

	/**
	 * renders the menu and all entries
	 *
	 * @see \vxPHP\Webpage\Menu\MenuInterface::render()
	 */
	public function render($showSubmenus = FALSE, $forceActive = FALSE, $options = NULL) {
		$this->showSubmenus = $showSubmenus;
		$this->forceActive = $forceActive;

		$markup = '';

		foreach($this->entries as $e) {
			$markup .= $e->render();
		}

		return sprintf('<ul>%s</ul>', $markup);
	}
}
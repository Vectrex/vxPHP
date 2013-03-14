<?php

namespace vxPHP\Webpage\Menu;

use vxPHP\Webpage\Menu\MenuInterface;

use vxPHP\Webpage\MenuEntry\MenuEntry;
use vxPHP\Webpage\MenuEntry\DynamicMenuEntry;

/**
 * Menu class
 *
 * manages a complete menu
 * @version 0.6.12 2012-09-14
 */
class Menu implements MenuInterface {
	protected	$id,
				$script,
				$type,
				$method,
				$auth,
				$authParameters,
				$entries = array(),
				$dynamicEntries = array(),
				$selectedEntry,
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
			foreach($this->entries as $k => &$e) {
				if($e instanceOf DynamicMenuEntry) {
					array_splice($this->entries, $k, 1);
					$e = NULL;
				}
			}
			$this->dynamicEntries = array();
		}
	}

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

	public function insertEntries(Array $entries, $ndx) {
		foreach($this->entries as $e) {
			$this->insertEntry($e, $ndx++);
		}
	}

	public function appendEntry(MenuEntry $entry) {
		$this->insertEntry($entry);
	}

	public function insertBeforeEntry(MenuEntry $new, MenuEntry $pos) {
		foreach($this->entries as $k => $e) {
			if($e === $pos) {
				$this->insertEntry($new, $k);
				break;
			}
		}
	}

	public function getEntryAtPos($ndx) {
		return $this->entries[$ndx];
	}

	public function replaceEntry(MenuEntry $new, MenuEntry &$toReplace) {
		foreach($this->entries as $k => $e) {
			if($e === $toReplace) {
				$this->insertEntry($new, $k);
				$this->removeEntry($toReplace);
				break;
			}
		}
	}

	public function removeEntry(MenuEntry &$toRemove) {
		foreach($this->entries as $k => $e) {
			if($e === $toRemove) {
				array_splice($this->entries, $k, 1);

				if($toRemove instanceof DynamicMenuEntry) {
					$ndx = array_search($toRemove, $this->dynamicEntries, true);
					if($ndx !== FALSE) {
						array_splice($this->dynamicEntries, $ndx, 1);
					}
				}

				$toRemove = NULL;
				break;
			}
		}
	}

	public function purgeEntries() {
		$this->entries = array();
		$this->dynamicEntries = array();
	}

	public function getId() {
		return $this->id;
	}

	public function getType() {
		return $this->type;
	}

	public function getMethod() {
		return $this->method;
	}

	public function getEntries() {
		return $this->entries;
	}

	public function getParentEntry() {
		return $this->parentEntry;
	}

	public function setParentEntry(MenuEntry $e) {
		$this->parentEntry = $e;
	}

	public function getScript() {
		return $this->script;
	}

	public function getSelectedEntry() {
		return $this->selectedEntry;
	}

	public function setSelectedEntry(MenuEntry $e) {
		$this->selectedEntry = $e;
	}

	public function clearSelectedEntry() {
		$this->selectedEntry = NULL;
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

	public function getForceActive() {
		return !!$this->forceActive;
	}

	public function setForceActive($state) {
		$this->forceActive = !!$state;
	}

	public function getShowSubmenus() {
		return !!$this->showSubmenus;
	}

	public function setShowSubmenus($state) {
		$this->showSubmenus = !!$state;
	}

	public function getDynamicEntries() {
		return $this->dynamicEntries;
	}

	public function render($showSubmenus = FALSE, $forceActive = FALSE) {
		$this->showSubmenus = $showSubmenus;
		$this->forceActive = $forceActive;

		$markup = '';

		foreach($this->entries as $e) {
			$markup .= $e->render();
		}

		return sprintf('<ul>%s</ul>', $markup);
	}
}
<?php
/**
 * Menu class
 * manages a complete menu
 * @version 0.6.7 2011-12-17
 * 
 * @todo a dynamic top-level menu won't be reset
 */
class Menu {
	protected	$id,
				$script,
				$type,
				$method,
				$auth,
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
		foreach($this->entries as $k => &$e) {
			if($e instanceOf DynamicMenuEntry) {
				array_splice($this->entries, $k, 1);
				$e = NULL;
			}
		}

		$this->dynamicEntries = array();
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

/**
 * abstract class for various menu decorators 
 */
abstract class MenuDecorator {
	protected $menu;
	
	public function __construct(Menu $menu) {
		$this->menu = $menu;
	}
}

class MenuDecoratorTagWrap extends MenuDecorator {
	public function render($showSubmenus = FALSE, $forceActive = FALSE, Array $tags = array()) {

		$this->menu->setShowSubmenus($showSubmenus);
		$this->menu->setForceActive($forceActive);

		$markup = '';

		foreach($this->menu->getEntries() as $e) {
			$d = new MenuEntryDecoratorTagWrap($e);
			$markup .= $d->render($tags);
		}

		return sprintf("<ul>\n%s</ul>", $markup);
	}
}

class MenuDecoratorMultiColumn extends MenuDecorator {
	public function render($showSubmenus = FALSE, $forceActive = FALSE, $entriesPerColumn) {
		$this->menu->setShowSubmenus($showSubmenus);
		$this->menu->setForceActive($forceActive);

		$markup = '';
		$cnt = 0;
		$entries = $this->menu->getEntries(); 

		foreach($entries as $e) {
			if(!($cnt % $entriesPerColumn)) {
				if(!$cnt) {
					$markup .= '<div class="firstColumn"><ul>';
				}
				else if(count($entries) <= $cnt + $entriesPerColumn) {
					$markup .= '</ul></div><div class="lastColumn"><ul>';
				}
				else {
					$markup .= '</ul></div><div class="nextColumn"><ul>';
				}
			}
			$markup .= $e->render();
			++$cnt;
		}

		return sprintf('%s</ul></div>', $markup);
	}
}

class MenuDecoratorAllowMarkup extends MenuDecorator {
	public function render($showSubmenus = FALSE, $forceActive = FALSE) {

		$this->menu->setShowSubmenus($showSubmenus);
		$this->menu->setForceActive($forceActive);

		$markup = '';

		foreach($this->menu->getEntries() as $e) {
			$d = new MenuEntryDecoratorAllowMarkup($e);
			$markup .= $d->render();
		}

		return sprintf('<ul>%s</ul>', $markup);
	}
}

/**
 * MenuEntry class
 * manages a single menu entry
 */
class MenuEntry {
	private static		$href;
	protected static	$count = 1;
	protected			$menu,
						$auth,
						$attributes,
						$id,
						$page,
						$subMenu,
						$localPage;

	public function __construct($page, $attributes, $localPage = TRUE) {
		$this->id			= self::$count++;
		$this->page			= $page;
		$this->localPage	= (boolean) $localPage;
		$this->attributes	= new stdClass();

		foreach($attributes as $attr => $value) {
			$attr = strtolower($attr);
			$this->attributes->$attr = (string) $value;
		}
	}

	// purge dynamically generated submenus
	
	public function __destruct() {
		if($this->subMenu && $this->subMenu->getType() == 'dynamic') {
			$this->subMenu->purgeEntries();
			$this->subMenu->clearSelectedEntry();
		}
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

	public function isAuthenticatedBy($privilege) {
		return isset($this->auth) && $privilege >= $this->auth;
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
	
	public function getAttributes() {
		return $this->attributes;
	}

	public function render() {

		if(isset($this->attributes->display) && $this->attributes->display == 'none') {
			return;
		}

		if(!isset(self::$href)) {
			self::$href = "{$this->menu->getScript()}?page=";
		}

		$sel = $this->menu->getSelectedEntry();

		if(isset($this->attributes->text)) {
			if(!isset($sel) || $sel !== $this) {
				$markup = sprintf(
					'<li class="%s"><a href="%s">%s</a>',
					preg_replace('~[^\w]~', '_', $this->page),
					$this->localPage ? NiceURI::autoConvert(self::$href.$this->page) : $this->page,
					htmlspecialchars($this->attributes->text)
				);
			}
			else {
				if((!isset($this->subMenu) || is_null($this->subMenu->getSelectedEntry())) && !$this->menu->getForceActive()) {
					$markup = sprintf(
						'<li class="active %s">%s',
						preg_replace('~[^\w]~', '_', $this->page),
						htmlspecialchars($this->attributes->text)
					);
				}
				else {
					$markup = sprintf(
						'<li class="active %s"><a href="%s">%s</a>',
						preg_replace('~[^\w]~', '_', $this->page),
						$this->localPage ? NiceURI::autoConvert(self::$href.$this->page) : $this->page,
						htmlspecialchars($this->attributes->text)
					);
				}
	
				if(isset($this->subMenu) && $this->menu->getShowSubmenus()) {
					$markup .= $this->subMenu->render();
				}
			}
			return $markup.'</li>';
		}

		return '';
	}
}

class DynamicMenuEntry extends MenuEntry {
	public function __construct($page, $attributes) {
		parent::__construct($page, $attributes);
	}

	public function setPage($page) {
		$this->page = $page;
	}

	public function setAttributes(Array $attributes) {
		foreach($attributes as $attr => $value) {
			$this->attributes->$attr = $value;
		}
	}
	
	public function __destruct() {
		parent::__destruct();
	}
}

/**
 * abstract class for various menu entry decorators 
 */
abstract class MenuEntryDecorator {
	protected $menuEntry;
	protected static $href;

	public function __construct(MenuEntry $menuEntry) {
		$this->menuEntry = $menuEntry;
	}
}

/**
 * menu entry decorator which wraps entries in additional tags
 */
class MenuEntryDecoratorTagWrap extends MenuEntryDecorator {

	public function render(Array $wrappingTags) {

		$openTags	= strtolower('<'.implode('><', $wrappingTags).'>');
		$closeTags	= strtolower('</'.implode('></', array_reverse($wrappingTags)).'>');

		$menu 		= $this->menuEntry->getMenu();
		$page 		= $this->menuEntry->getPage();
		$subMenu	= $this->menuEntry->getSubmenu();
		$attr		= $this->menuEntry->getAttributes();
		$localPage	= $this->menuEntry->isLocalPage();

		if(!isset(self::$href)) {
			self::$href = "{$menu->getScript()}?page=";
		}

		$sel = $menu->getSelectedEntry();

		if(!isset($sel) || $sel !== $this->menuEntry) {
			return sprintf(
				"<li class='%s'>\n%s\n<a href='%s'>%s</a>\n%s\n</li>\n",
				preg_replace('~[^\w]~', '_', $page),
				$openTags,
				$localPage ? NiceURI::autoConvert(self::$href.$page) : $page,
				htmlspecialchars($attr->text),
				$closeTags
			);
		}
		else {
			$isAnchor = !empty($subMenu) && !is_null($subMenu->getSelectedEntry()) || $menu->getForceActive(); 

			if(!empty($subMenu) && $menu->getShowSubmenus()) {
				$sm = new MenuDecoratorTagWrap($subMenu);
				$smMarkup = $sm->render(TRUE, $menu->getForceActive(), $wrappingTags);
			}
			else {
				$smMarkup = '';
			}

			return sprintf(
				"<li class='active %s'>\n%s\n%s\n%s\n%s\n</li>\n",
				preg_replace('~[^\w]~', '_', $page),
				$openTags,
				$isAnchor ?
					sprintf("<a href='%s'>%s</a>", $localPage ? NiceURI::autoConvert(self::$href.$page) : $page, htmlspecialchars($attr->text)) :
					htmlspecialchars($attr->text),
				$closeTags,
				$smMarkup
			);
		}
	}
}

/**
 * menu entry decorator which won't sanitize menu entry text
 */
class MenuEntryDecoratorAllowMarkup extends MenuEntryDecorator {

	public function render() {

		$menu 		= $this->menuEntry->getMenu();
		$page 		= $this->menuEntry->getPage();
		$subMenu	= $this->menuEntry->getSubmenu();
		$attr		= $this->menuEntry->getAttributes();
		$localPage	= $this->menuEntry->isLocalPage();

		if(!isset(self::$href)) {
			self::$href = "{$this->menu->getScript()}?page=";
		}
		$sel = $menu->getSelectedEntry();

		if(!isset($sel) || $sel !== $this->menuEntry) {
			$markup = sprintf(
				'<li class="%s"><a href="%s">%s</a>',
				preg_replace('~[^\w]~', '_', $page),
				$localPage ? NiceURI::autoConvert(self::$href.$page) : $page,
				$attr->text
			);
		}
		else {
			if((!isset($subMenu) || is_null($subMenu->getSelectedEntry())) && !$menu->getForceActive()) {
				$markup = sprintf(
					'<li class="active %s">%s',
					preg_replace('~[^\w]~', '_', $page),
					$attr->text
				);
			}
			else {
				$markup = sprintf(
					'<li class="active %s"><a href="%s">%s</a>',
					preg_replace('~[^\w]~', '_', $page),
					$localPage ? NiceURI::autoConvert(self::$href.$page) : $page,
					$attr->text
				);
			}

			if(isset($subMenu) && $menu->getShowSubmenus()) {
				$markup .= $subMenu->render();
			}
		}
		return $markup.'</li>';
	}
}
?>

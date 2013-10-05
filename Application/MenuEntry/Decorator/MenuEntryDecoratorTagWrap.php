<?php

namespace vxPHP\Application\MenuEntry\Decorator;

use vxPHP\Application\MenuEntry\Decorator\MenuEntryDecorator;
use vxPHP\Application\Menu\Decorator\MenuDecoratorTagWrap;

use vxPHP\Application\Webpage\NiceURI;

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
		$localPage	= $this->menuEntry->refersLocalPage();

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

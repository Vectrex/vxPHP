<?php

namespace vxPHP\Webpage\MenuEntry\Decorator;

use vxPHP\Webpage\MenuEntry\Decorator\MenuEntryDecorator;
use vxPHP\Request\NiceURI;

/**
 * menu entry decorator which won't sanitize menu entry text
 */
class MenuEntryDecoratorAllowMarkup extends MenuEntryDecorator {

	public function render() {

		$menu 		= $this->menuEntry->getMenu();
		$page 		= $this->menuEntry->getPage();
		$subMenu	= $this->menuEntry->getSubmenu();
		$attr		= $this->menuEntry->getAttributes();
		$localPage	= $this->menuEntry->refersLocalPage();

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

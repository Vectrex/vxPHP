<?php

namespace vxPHP\Application\Menu\Decorator;

use vxPHP\Application\Menu\Decorator\MenuDecorator;
use vxPHP\Application\MenuEntry\Decorator\MenuEntryDecoratorTagWrap;

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

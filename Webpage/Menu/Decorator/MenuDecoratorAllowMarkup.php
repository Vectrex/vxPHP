<?php

namespace vxPHP\Webpage\Menu\MenuDecorator;

use vxPHP\Webpage\Menu\Decorator\MenuDecorator;
use vxPHP\Webpage\MenuEntry\Decorator\MenuEntryDecoratorAllowMarkup;

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

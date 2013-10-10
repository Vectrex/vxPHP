<?php

namespace vxPHP\Application\Menu\MenuDecorator;

use vxPHP\Application\Menu\Decorator\MenuDecorator;
use vxPHP\Application\MenuEntry\Decorator\MenuEntryDecoratorAllowMarkup;
use vxPHP\Application\Menu\MenuInterface;

class MenuDecoratorAllowMarkup extends MenuDecorator implements MenuInterface {
	public function render($showSubmenus = FALSE, $forceActive = FALSE, $options = NULL) {

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

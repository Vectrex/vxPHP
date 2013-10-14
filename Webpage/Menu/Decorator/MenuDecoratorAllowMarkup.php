<?php

namespace vxPHP\Webpage\Menu\MenuDecorator;

use vxPHP\Webpage\Menu\Decorator\MenuDecorator;
use vxPHP\Webpage\MenuEntry\Decorator\MenuEntryDecoratorAllowMarkup;
use vxPHP\Webpage\Menu\MenuInterface;

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

<?php

namespace vxPHP\Webpage\Menu\Decorator;

use vxPHP\Webpage\Menu\Decorator\MenuDecorator;

class MenuDecoratorMultiColumn extends MenuDecorator {
	public function render($showSubmenus = FALSE, $forceActive = FALSE, $entriesPerColumn) {
		$this->menu->setShowSubmenus($showSubmenus);
		$this->menu->setForceActive($forceActive);

		$markup = '';
		$cnt = 0;

		$entriesMarkup = array();
		foreach ($this->menu->getEntries() as $e) {
			$m = $e->render();
			if($m) {
				$entriesMarkup[] = $m;
			}
		}

		foreach($entriesMarkup as $m) {
			if(!($cnt % $entriesPerColumn)) {
				if(!$cnt) {
					$markup .= '<div class="firstColumn"><ul>';
				}
				else if(count($entriesMarkup) <= $cnt + $entriesPerColumn) {
					$markup .= '</ul></div><div class="lastColumn"><ul>';
				}
				else {
					$markup .= '</ul></div><div class="nextColumn"><ul>';
				}
			}
			$markup .= $m;
			++$cnt;
		}

		return sprintf('%s</ul></div>', $markup);
	}
}

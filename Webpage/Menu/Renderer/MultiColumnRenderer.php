<?php

namespace vxPHP\Webpage\Menu\Renderer;

use vxPHP\Webpage\Menu\Renderer\MenuRendererInterface;
use vxPHP\Webpage\Menu\Renderer\MenuRenderer;
use vxPHP\Webpage\Menu\Menu;
use vxPHP\Webpage\MenuEntry\MenuEntry;
use vxPHP\Application\Application;

/**
 * renders menu in a multicolumn layout
 *
 * @author Gregor Kofler
 */
class MultiColumnRenderer extends SimpleListRenderer implements MenuRendererInterface {

	public function render() {

		$markup	= '';
		$cnt	= 0;

		if(!isset($this->parameters['entriesPerColumn'])) {
			//@todo throw exception or initialize default value
		}

		$entriesMarkup = array();

		foreach ($this->menu->getEntries() as $e) {
			$m = $this->renderEntry($e);

			if($m) {
				$entriesMarkup[] = $m;
			}
		}

		foreach($entriesMarkup as $m) {

			if(!($cnt % $this->parameters['entriesPerColumn'])) {
				if(!$cnt) {
					$markup .= '<div class="firstColumn"><ul>';
				}
				else if(count($entriesMarkup) <= $cnt + $this->parameters['entriesPerColumn']) {
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
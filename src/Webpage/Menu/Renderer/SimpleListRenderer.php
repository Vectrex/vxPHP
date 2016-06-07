<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Webpage\Menu\Renderer;

use vxPHP\Webpage\Menu\Renderer\MenuRendererInterface;
use vxPHP\Webpage\Menu\Renderer\MenuRenderer;
use vxPHP\Webpage\Menu\Menu;
use vxPHP\Webpage\MenuEntry\MenuEntry;
use vxPHP\Application\Application;

/**
 * renderer renders menu in a ul-li markup structure
 * submenus are nested
 * every menu entry wrapped in tags when a parameter 'wrappingTags', defining these tags, is set
 *
 * @author Gregor Kofler
 */

class SimpleListRenderer extends MenuRenderer implements MenuRendererInterface {

	private $openingTags,
			$closingTags;

	public function render() {

		// create seqeunce of opening tags and closing tags

		if(isset($this->parameters['wrappingTags'])) {

			if(!is_array(($tags = $this->parameters['wrappingTags']))) {
				$tags = preg_split('/\s*,\s*/', $tags);
			}

			$this->openingTags	= strtolower('<'.implode('><', $tags).'>');
			$this->closingTags	= strtolower('</'.implode('></', array_reverse($tags)).'>');
		}

		$markup = '';

		foreach($this->menu->getEntries() as $e) {
			$markup .= $this->renderEntry($e);
		}

		return sprintf('<ul>%s</ul>', $markup);

	}

	/**
	 * @see \vxPHP\Webpage\Menu\Renderer\MenuRenderer::renderEntry()
	 */
	protected function renderEntry(MenuEntry $entry) {

		$attributes = $entry->getAttributes();
		
		// check display attribute

		if(!isset($attributes->display) || $attributes->display !== 'none') {

			if($this->hasNiceUris) {

				if(($script = basename($this->menu->getScript(), '.php')) == 'index') {
					$script = '/';
				}
				else {
					$script = '/'. $script . '/';
				}

			}

			else {
				$script = '/' . $this->menu->getScript() . '/';
			}

			$sel = $this->menu->getSelectedEntry();

			if(isset($attributes->text)) {

				// render a not selected menu entry

				if(!isset($sel) || $sel !== $entry) {
					$markup = sprintf(
						'<li class="%s">%s<a href="%s">%s</a>%s',
						preg_replace('~[^\w]~', '_', $entry->getPath()),
						$this->openingTags,
						$entry->getHref(),
						empty($this->parameters['rawText']) ? htmlspecialchars($attributes->text) : $attributes->text,
						$this->closingTags
					);
					
					// ensure rendering of submenus, when a parameter "unfoldAll" is set

					if(!empty($this->parameters['unfoldAll']) && ($subMenu = $entry->getSubMenu())) {
						$markup .= static::create($subMenu)->setParameters($this->parameters)->render();
					}
				}

				else {

					// render a selected menu entry

					if((!$entry->getSubMenu() || is_null($entry->getSubMenu()->getSelectedEntry())) && !$this->menu->getForceActive()) {

						$markup = sprintf(
							'<li class="active %s">%s<span>%s</span>%s',
							preg_replace('~[^\w]~', '_', $entry->getPath()),
							$this->openingTags,
							empty($this->parameters['rawText']) ? htmlspecialchars($attributes->text) : $attributes->text,
							$this->closingTags
						);

					}

					else {

						$markup = sprintf(
							'<li class="active %s">%s<a href="%s">%s</a>%s',
							preg_replace('~[^\w]~', '_', $entry->getPath()),
							$this->openingTags,
							$entry->getHref(),
							empty($this->parameters['rawText']) ? htmlspecialchars($attributes->text) : $attributes->text,
							$this->closingTags
						);

					}

					if($this->menu->getShowSubmenus() && ($subMenu = $entry->getSubMenu())) {
						$markup .= static::create($subMenu)->setParameters($this->parameters)->render();
					}
				}

				return $markup.'</li>';
			}
		}
	}
}
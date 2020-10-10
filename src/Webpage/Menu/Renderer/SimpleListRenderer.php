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

use vxPHP\Application\Exception\ApplicationException;
use vxPHP\Webpage\MenuEntry\MenuEntry;

/**
 * renderer renders menu in a ul-li markup structure
 * submenus are nested
 * every menu entry wrapped in tags when a parameter 'wrappingTags', defining these tags, is set
 *
 * @version 0.3.1, 2020-10-10
 *
 * @author Gregor Kofler
 */

class SimpleListRenderer extends MenuRenderer
{
	/**
	 * stringified opening tags of the wrappingTags parameter
	 *
	 * @var string
	 *
	 */
	private $openingTags;

	/**
	 * stringified closing tags of the wrappingTags parameter  
	 * 
	 * @var string
	 *
	 */
	private $closingTags;

    /**
     * @return string
     * @throws ApplicationException
     */
    public function render(): string
    {
        if ($this->menu->getAttribute('display') === 'none') {
            return '';
        }

		// create seqeunce of opening tags and closing tags

		if(isset($this->parameters['wrappingTags'])) {

			if(!is_array(($tags = $this->parameters['wrappingTags']))) {
				$tags = preg_split('/\s*,\s*/', $tags);
			}

			$this->openingTags = strtolower('<'.implode('><', $tags).'>');
			$this->closingTags = strtolower('</'.implode('></', array_reverse($tags)).'>');
		}

		$markup = '';

		foreach($this->menu->getEntries() as $e) {
			$markup .= $this->renderEntry($e);
		}

		return sprintf(
			'<ul%s>%s</ul>',
			isset($this->parameters['ulClass']) ? (' class="' . $this->parameters['ulClass'] . '"') : '',
			$markup
		);
	}

    /**
     * @param MenuEntry $entry
     * @return string
     * @throws ApplicationException
     * @see \vxPHP\Webpage\Menu\Renderer\MenuRenderer::renderEntry()
     */
	protected function renderEntry (MenuEntry $entry): string
    {
		// check display attribute

		if($entry->getAttribute('display') !== 'none') {

			$sel = $this->menu->getSelectedEntry();

			if($text = $entry->getAttribute('text')) {

				// render a not selected menu entry

				if(!isset($sel) || $sel !== $entry) {

					$markup = sprintf(
						'<li class="%s">%s<a href="%s">%s</a>%s',
						preg_replace('~[^\w]~', '_', $entry->getPath()) . (isset($this->parameters['liClass']) ? (' ' . $this->parameters['liClass']) : ''),
						$this->openingTags,
						$entry->getHref(),
						empty($this->parameters['rawText']) ? htmlspecialchars($text) : $text,
						$this->closingTags
					);

					// ensure rendering of submenus, when a parameter "unfoldAll" is set

					if(!empty($this->parameters['unfoldAll']) && ($subMenu = $entry->getSubMenu())) {
						$markup .= static::create($subMenu)->setParameters($this->parameters)->render();
					}
				}

				else {

					// ensure rendering of submenus, when a parameter "unfoldAll" is set, this overrides the showSubmenus property of the menu

					if((!$entry->getSubMenu() || is_null($entry->getSubMenu()->getSelectedEntry())) && !$this->menu->getForceActive()) {
						$markup = sprintf(
							'<li class="active %s">%s<span>%s</span>%s',
							preg_replace('~[^\w]~', '_', $entry->getPath()) . (isset($this->parameters['liClass']) ? (' ' . $this->parameters['liClass']) : ''),
							$this->openingTags,
							empty($this->parameters['rawText']) ? htmlspecialchars($text) : $text,
							$this->closingTags
						);
					}
					else {
						$markup = sprintf(
							'<li class="active %s">%s<a href="%s">%s</a>%s',
							preg_replace('~[^\w]~', '_', $entry->getPath()) . (isset($this->parameters['liClass']) ? (' ' . $this->parameters['liClass']) : ''),
							$this->openingTags,
							$entry->getHref(),
							empty($this->parameters['rawText']) ? htmlspecialchars($text) : $text,
							$this->closingTags
						);
					}

					// ensure rendering of submenus, when a parameter "unfoldAll" is set, this overrides the showSubmenus property of the menu

					if(!empty($this->parameters['unfoldAll']) && ($subMenu = $entry->getSubMenu())) {
						$markup .= static::create($subMenu)->setParameters($this->parameters)->render();
					}
				}

				return $markup . '</li>';
			}
		}

		return '';
	}
}
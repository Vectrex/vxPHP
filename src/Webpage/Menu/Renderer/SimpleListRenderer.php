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

use vxPHP\Webpage\MenuEntry\MenuEntry;

/**
 * renderer renders menu with nested submenus in an ul-li-a markup structure
 * the following parameters can be used:
 * - wrappingTags: a whitespace separated lists of tag names which will wrap the inner HTML of the li elements (default empty string)
 * - ulClass: additional class(es) set on every ul element (default empty string)
 * - liClass: additional class(es) set on every li element (default empty string)
 * - aClass: additional class(es) set on every anchor element (default empty string)
 * - spanClass: additional class(es) set on every span element (default empty string)
 * - activeClass: class(es) which indicate an active menu entry (default "active")
 * - rawText: boolean which prevents masking of special HTML chars (default false)
 * - unfoldAll: forces rendering of *all* submenus (defaults to false)
 *
 * @version 0.5.1, 2025-01-13
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
    private string $openingTags = '';

    /**
     * stringified closing tags of the wrappingTags parameter
     *
     * @var string
     *
     */
    private string $closingTags = '';

    /**
     * @return string
     */
    public function render(): string
    {
        if (!$this->menu->getDisplay()) {
            return '';
        }

        // create seqeunce of opening tags and closing tags

        if (isset($this->parameters['wrappingTags'])) {

            if (!is_array(($tags = $this->parameters['wrappingTags']))) {
                $tags = preg_split('/\s*,\s*/', $tags);
            }

            $this->openingTags = strtolower('<' . implode('><', $tags) . '>');
            $this->closingTags = strtolower('</' . implode('></', array_reverse($tags)) . '>');
        }

        $markup = '';

        foreach ($this->menu->getEntries() as $e) {
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
     * @see \vxPHP\Webpage\Menu\Renderer\MenuRenderer::renderEntry()
     */
    protected function renderEntry(MenuEntry $entry): string
    {
        // check display attribute

        if (!$entry->getDisplay() || !($text = $entry->getAttribute('text'))) {
            return '';
        }

        $sel = $this->menu->getSelectedEntry();

        // render a not selected menu entry

        if (!isset($sel) || $sel !== $entry) {

            $markup = sprintf(
                '<li class="%s">%s<a %s href="%s">%s</a>%s',
                preg_replace('~[^\w]~', '_', $entry->getPath()) . (isset($this->parameters['liClass']) ? (' ' . $this->parameters['liClass']) : ''),
                $this->openingTags,
                isset($this->parameters['aClass']) ? (' class="' . $this->parameters['aClass'] . '"') : '',
                $entry->getHref(),
                empty($this->parameters['rawText']) ? htmlspecialchars($text) : $text,
                $this->closingTags
            );

            // ensure rendering of submenus, when a parameter "unfoldAll" is set

            if (!empty($this->parameters['unfoldAll']) && ($subMenu = $entry->getSubMenu())) {
                $markup .= static::create($subMenu)->setParameters($this->parameters)->render();
            }
        } else {

            // ensure rendering of submenus, when a parameter "unfoldAll" is set, this overrides the showSubmenus property of the menu

            if ((!$entry->getSubMenu() || is_null($entry->getSubMenu()->getSelectedEntry())) && !$this->menu->getForceActive()) {
                $markup = sprintf(
                    '<li class="%s %s">%s<span%s>%s</span>%s',
                    $this->parameters['activeClass'] ?? 'active',
                    preg_replace('~[^\w]~', '_', $entry->getPath()) . (isset($this->parameters['liClass']) ? (' ' . $this->parameters['liClass']) : ''),
                    $this->openingTags,
                    isset($this->parameters['spanClass']) ? (' class="' . $this->parameters['spanClass'] . '"') : '',
                    empty($this->parameters['rawText']) ? htmlspecialchars($text) : $text,
                    $this->closingTags
                );
            } else {
                $markup = sprintf(
                    '<li class="%s %s">%s<a href="%s">%s</a>%s',
                    $this->parameters['activeClass'] ?? 'active',
                    preg_replace('~[^\w]~', '_', $entry->getPath()) . (isset($this->parameters['liClass']) ? (' ' . $this->parameters['liClass']) : ''),
                    $this->openingTags,
                    $entry->getHref(),
                    empty($this->parameters['rawText']) ? htmlspecialchars($text) : $text,
                    $this->closingTags
                );
            }

            // ensure rendering of submenus, when a parameter "unfoldAll" is set, this overrides the showSubmenus property of the menu

            if (!empty($this->parameters['unfoldAll']) && ($subMenu = $entry->getSubMenu())) {
                $markup .= static::create($subMenu)->setParameters($this->parameters)->render();
            }
        }

        return $markup . '</li>';
    }
}
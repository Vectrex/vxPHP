<?php

/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Template\Filter;

/**
 * Simple filter that shortens a text between its opening and closing tag
 * to specified count of characters; this count is determined by value of class attribute
 * of enclosing tag (e.g. <p class="shortened_10">This will be very short.</p>
 * text will only be shortened along word boundaries
 *
 * @author Gregor Kofler
 */
class Spaceless extends SimpleTemplateFilter implements SimpleTemplateFilterInterface
{
	/**
	 * {@inheritDoc}
	 * @see \vxPHP\Template\Filter\SimpleTemplateFilter::apply()
	 */
	public function apply(&$templateString): void
    {
		$templateString = trim(preg_replace('/>\s+</', '><', $templateString));
	}
}


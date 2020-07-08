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
 * Removes whitespaces between HTML tags.
 * Only active in <!-- { spaceless } --> <!-- { endspaceless } --> blocks
 * no checks for nested or missing opening/closing directives are performed
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
        $templateString = preg_replace_callback(
            '~<!--\s*{\s*spaceless\s*}\s*-->(.*?)<!--\s*{\s*endspaceless\s*}\s*-->~s',
            static function($matches) {
                return trim(preg_replace('~>\s+<~', '><', $matches[1]));
            },
            $templateString
        );
	}
}


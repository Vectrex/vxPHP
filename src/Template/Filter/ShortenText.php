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
class ShortenText extends SimpleTemplateFilter implements SimpleTemplateFilterInterface
{
	/**
	 * {@inheritDoc}
	 * @see \vxPHP\Template\Filter\SimpleTemplateFilter::apply()
	 */
	public function apply(&$templateString): void
    {

		$templateString = preg_replace_callback(
			'~<(\w+)\s+(.*?)class=(\'|")?([a-z0-9_]*\s*)shortened_(\d+)(.*?)>(?s)(.*?)(?-s)</\s*\1>~i',
			function($matches) {
				$prefix = sprintf("<%s %sclass=%s%sshortened%s>", $matches[1], $matches[2], $matches[3], $matches[4], $matches[6]);
				$suffix = sprintf("</%s>", $matches[1]);
				$len = (int) $matches[5];
				$src = $matches[7];
				
				if(strlen(strip_tags($src)) <= $len) {
					return $prefix . $src . $suffix;
				}
				
				$ret = substr(strip_tags($src), 0, $len + 1);
				return $prefix . substr($ret,0,strrpos($ret,' ')) . '&hellip;' . $suffix;
			},
			$templateString
		);
	}
}

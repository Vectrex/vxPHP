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
class ShortenText extends SimpleTemplateFilter implements SimpleTemplateFilterInterface {

	/**
	 * (non-PHPdoc)
	 *
	 * @see \vxPHP\SimpleTemplate\Filter\SimpleTemplateFilterInterface::parse()
	 *
	 */
	public function apply(&$templateString) {

		$templateString = preg_replace_callback(
			'~<(\w+)\s+(.*?)class=(\'|")?([a-z0-9_]*\s*)shortened_(\d+)(.*?)>(?s)(.*?)(?-s)</\s*\1>~i',
			array($this, 'shortenText'),
			$templateString
		);
	}

	private function shortenText($matches) {

		$prefix = "<{$matches[1]} {$matches[2]}class={$matches[3]}{$matches[4]}shortened{$matches[6]}>";
		$suffix = "</{$matches[1]}>";
		$len = (int) $matches[5];
		$src = $matches[7];

		if(strlen(strip_tags($src)) <= $len) {
			return $prefix . $src . $suffix;
		}

		$ret = substr(strip_tags($src), 0, $len + 1);
		return $prefix . substr($ret,0,strrpos($ret,' ')) . '&hellip;' . $suffix;

	}
}

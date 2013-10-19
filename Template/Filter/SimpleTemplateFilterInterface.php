<?php

namespace vxPHP\SimpleTemplate\Filter;

/**
 *
 * @author Gregor Kofler
 *
 */
interface SimpleTemplateFilterInterface {

	/**
	 *
	 * @param string $templateString
	 */
	public static function parse(&$templateString);
}

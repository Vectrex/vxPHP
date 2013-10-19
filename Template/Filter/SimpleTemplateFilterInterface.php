<?php

namespace vxPHP\SimpleTemplate\Filter;

/**
 *
 * @author Gregor Kofler
 *
 */
interface SimpleTemplateFilterInterface {

	/**
	 * apply filter to template string
	 * template string is passed by reference
	 *
	 * @param string $templateString
	 *
	 * @return void
	 */
	public function apply(&$templateString);
}

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

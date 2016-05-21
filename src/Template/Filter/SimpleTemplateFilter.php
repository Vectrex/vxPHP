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
 * @author Gregor Kofler
 */
abstract class SimpleTemplateFilter {

	public function __construct() {
	}

	public static function create() {
		return new static();
	}

	public abstract function apply(&$templateString);
}

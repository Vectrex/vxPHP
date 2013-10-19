<?php

namespace vxPHP\SimpleTemplate\Filter;

/**
 * @author Gregor Kofler
 */
abstract class SimpleTemplateFilter {

	public function __construct() {
	}

	public static function create() {
		return new static();
	}

	public abstract function apply();
}

<?php

namespace vxPHP\Util;

/**
 *
 * @author Gregor Kofler
 * @todo currently a stub
 *
 * @version 0.1.0 2013-09-08
 *
 */
class Locale {

	private $localeString;

	public function __construct($localeString) {
		$this->localeString = strtolower($localeString);
	}

	public function set() {
		setlocale(LC_ALL, $this->localeString);
	}

	public function getLocaleString() {
		return $this->localeString;
	}
}

<?php

namespace vxPHP\Util;

/**
 *
 * @author Gregor Kofler
 * @todo currently a stub
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

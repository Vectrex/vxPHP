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
		$this->localeString = $localeString;
	}

	public function set() {
		setlocale(LC_ALL, $this->localeString);
	}
}

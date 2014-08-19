<?php

namespace vxPHP\Application\Locale;

/**
 *
 * @author Gregor Kofler
 * @todo currently a stub
 *
 * @version 0.2.0 2013-11-01
 *
 */
class Locale {

	private $localeId;

	public function __construct($localeId) {
		$this->localeId = strtolower($localeId);
	}

	public function set() {
		setlocale(LC_ALL, $this->$localeId);
	}

	public function getLocaleId() {
		return $this->localeId;
	}
}

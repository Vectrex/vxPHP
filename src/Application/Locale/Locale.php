<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Application\Locale;

/**
 *
 * @author Gregor Kofler
 * @todo currently a stub
 *
 * @version 0.3.0 2015-06-19
 *
 */
class Locale {

	private $localeId;

	public function __toString() {
		return (string) $this->localeId;
	}

	public function __construct($localeId) {
		$this->localeId = strtolower($localeId);
	}

	public function set() {
		setlocale(LC_ALL, $this->localeId);
	}

	public function getLocaleId() {
		return $this->localeId;
	}
}

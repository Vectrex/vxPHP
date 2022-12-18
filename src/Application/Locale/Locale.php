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
 * @version 0.4.0 2021-11-26
 *
 */
class Locale
{
	private string $localeId;

	public function __toString() {
		return $this->localeId;
	}

	public function __construct (string $localeId)
    {
		$this->localeId = strtolower($localeId);
	}

	public function set(): void
    {
		setlocale(LC_ALL, $this->localeId);
	}

	public function getLocaleId(): string
    {
		return $this->localeId;
	}
}

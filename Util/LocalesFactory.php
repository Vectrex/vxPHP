<?php

namespace vxPHP\Util;

use vxPHP\Util\Exception\LocalesFactoryException;
/**
 *
 * @author Gregor Kofler
 * @todo currently a stub
 *
 */
class LocalesFactory {

	private static $knownLocales = array(
		'de',
		'en'
	);

	public static function getLocale($localeString) {
		if(in_array($localeString, self::$knownLocales)) {
			return new Locale($localeString);
		}

		else {
			throw new LocalesFactoryException();
		}
	}

	public static function getAllowedLocales() {
		return self::$knownLocales;
	}
}
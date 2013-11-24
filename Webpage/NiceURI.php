<?php

namespace vxPHP\Webpage;

use vxPHP\Application\Application;
/**
 * provides static low level methods to convert nice uris to plain uris and vice-versa
 *
 * @author Gregor Kofler
 * @version 0.9.2 2013-11-24
 *
 */
class NiceURI {

	/**
	 * @var \vxPHP\Http\Request
	 */
	private static $request;

	/**
	 * @var array
	 */
	private static $knownBasenames = array(
		'admin'
	);

	/**
	 * transform "normal" uris to "nice" uris
	 * result will have the following structure
	 * /[{script_basename}][/{language}]/{path/to/page}
	 *
	 * @param string $plainUri
	 * @return string $niceUri
	 *
	 */
	public static function toNice($plainUri) {

		$components = parse_url($plainUri);

		if (!isset($components['path'])) {
			$path = '/';
		}
		else {
			$path = preg_replace(
				array(
					'~^index.php\/~i',
					'~^(' . implode('|', self::$knownBasenames) . ').php\/~i'
				),
				array(
					'/',
					'${1}/'
				),
				$components['path']
			);
		}

		return '/' . $path . (empty($components['query']) ? '' : '?' . $components['query']);
	}

	/**
	 * transform nice uris to plain uris
	 *
	 * @param $niceUri
	 * @return $plainUri
	 */
	public static function toPlain($niceUri) {


		$parts = explode('/', trim($niceUri, '/'));
		$uri = 'index.php';

		if(in_array($parts[0], self::$knownBasenames)) {
			$uri = Application::getInstance()->getRelativeAssetsPath() . array_shift($parts) . '.php';
		}

		return Application::getInstance()->getRelativeAssetsPath() . $uri . implode('/', $parts);

	}

	/**
	 * identifies plain URIs
	 *
	 * @param $uri
	 * @return boolean result
	 */
	public static function isPlainURI($uri) {
		return (boolean) preg_match('~^/?(\w+\.php|\?\w+)~', $uri);
	}

	/**
	 * converts plain to nice uris and vice-versa depending on configuration setting
	 *
	 * @param $uri
	 * @return converted uri
	 */
	public static function autoConvert($uri) {
		$isPlain = self::isPlainURI($uri);

		if($isPlain && Application::getInstance()->getConfig()->site->use_nice_uris) {
			$uri = self::toNice($uri);
		}
		else if(!$isPlain && !Application::getInstance()->getConfig()->site->use_nice_uris) {
			$uri = self::toPlain($uri);
		}

		return $uri;
	}
}

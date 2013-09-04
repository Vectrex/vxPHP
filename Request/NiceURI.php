<?php

namespace vxPHP\Request;

/**
 * provides static low level methods to convert nice uris to plain uris and vice-versa
 *
 * @author Gregor Kofler
 * @version 0.8.2 2010-11-06
 *
 */
class NiceURI {
	private static $locales;

	/**
	 * transform "normal" uris to "nice" uris
	 * result will have the following structure
	 * /[{script_basename}][/{language}]/{page}[/id][/key_1/value_1][/key_2/value_2]...[/key_n/value_n]
	 */
	public static function toNice($plainUri) {
		$doc = basename(Request::createFromGlobals()->server->get('SCRIPT_NAME'), '.php');
		if($doc == 'index') {
			$doc = '';
		}
		else {
			$doc = "/$doc";
		}

		$parts = explode('?', $plainUri);
		if(count($parts) < 2) {
			return $doc;
		}

		$query = explode('&', $parts[1]);
		$get = array();

		$lang = '';
		$page = '';
		$id = '';
		$other = '';

		foreach($query as $q) {
			$g = explode('=', $q);
			switch($g[0]) {
				case 'lang':
					$lang = '/'.$g[1];
					break;
				case 'page':
					$page = '/'.$g[1];
					break;
				case 'id':
					$id = '/'.$g[1];
					break;
				default:
					if(isset($g[1])) {
						$other .= '/'.$g[0].'/'.$g[1];
					}
					else {
						$other .= "/{$g[0]}/1";
					}
			}
		}

		return $doc.$lang.$page.$id.$other;
	}

	/**
	 * transform nice uris to plain uris
	 * @param $niceUri
	 */
	public static function toPlain($niceUri) {
		if(empty(self::$locales)) {
			self::$locales = $GLOBALS['config']->locales;
		}

		$parts = explode('/', trim($niceUri, '/'));
		$uri = '';

		if(empty($parts)) {
			return $uri;
		}

		$uri .= basename($_SERVER['SCRIPT_NAME']) != 'index.php' ? array_shift($parts).'.php' : '';

		if(!($next = array_shift($parts))) {
			return $uri;
		}

		if(array_key_exists($next, self::$locales)) {
			$uri .= "?lang=$next";
			if(($next = array_shift($parts))) {
				$uri .= "&page=$next";
			}
		}
		else {
			$uri .= "?page=$next";
		}

		if(!($count = count($parts))) {
			return $uri;
		}

		if($count % 2) {
			$uri .= '&id='.array_shift($parts);
		}

		while(!empty($parts)) {
			$v = array_pop($parts);
			$k = array_pop($parts);
			if(!is_numeric($k)) {
				$uri .= "&$k=$v";
			}
		}
		return $uri;
	}

	/**
	 * parses $_GET of $niceUri
	 *
	 * rules
	 * [/script_name][/locale]/page[/id|/key 1/value 1/key 2/value 2.../key n/value n][?tradional_get_parameters]
	 *
	 * - the script name is being discarded (since the mod_rewrite directives already deal with that)
	 * - optional first subpath is locale if string is key in $this->locales (lang=<locale>)
	 * - next subpath indicates the page (page=<page>)
	 * - if only one following parameter is supplied this one always refers to an id parameter (id=<id>)
	 * - otherwise parameters are evaluated in pairs - first one defines key, second one value (<key_1>=<value_1>)
	 *
	 * @param string nice uri to parse
	 * @return array _get
	 */
	public static function getNiceURI_GET($niceUri) {

		if(empty(self::$locales)) {
			self::$locales = $GLOBALS['config']->locales;
		}

		$parsed = array();

		// check for forms submitted by GET
		$checkForGet = explode('?', $niceUri);
		if(count($checkForGet) > 1) {
			$query = explode('/', trim($checkForGet[0], '/'));
			$get = $checkForGet[1];
		}
		else {
			$query = explode('/', trim($niceUri, '/'));
		}

		if(empty($query)) {
			return $parsed;
		}

		// skip optional script name
		if(basename($_SERVER['SCRIPT_NAME']) != 'index.php') {
			array_shift($query);
		}

		$first = array_shift($query);

		if(array_key_exists($first, self::$locales)) {
			$parsed['lang'] = $first;
			$first = array_shift($query);
		}
		if(isset($first)) {
			$parsed['page'] = $first;
		}

		if(empty($query) && empty($get)) {
			return $parsed;
		}

		// with odd "items" the first one is automatically assigned to "id"
		if(count($query) % 2) {
			$parsed['id'] = array_shift($query);
		}
		while(!empty($query)) {
			$v = array_pop($query);
			$parsed[array_pop($query)] = $v;
		}

		// append possible GET parameters
		if(!empty($get)) {
			parse_str($get, $add);
			$parsed = array_merge($parsed, $add);
		}

		return $parsed;
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

		if($isPlain && $GLOBALS['config']->site->use_nice_uris) {
			$uri = self::toNice($uri);
		}
		else if(!$isPlain && !$GLOBALS['config']->site->use_nice_uris) {
			$uri = self::toPlain($uri);
		}
		return $uri;
	}
}
?>
<?php

namespace vxPHP\Template;

use vxPHP\Template\Exception\SimpleTemplateException;
use vxPHP\Util\Rex;
use vxPHP\File\FilesystemFolder;
use vxPHP\Image\ImageModifier;
use vxPHP\Request\NiceURI;
use vxPHP\Request\Router;
use vxPHP\Request\Request;

/**
 * A simple template system
 *
 * @version 0.8.3 2013-04-06
 * @author Gregor Kofler
 * @todo regEx for shorten_text-filter breaks with boundary within tag or entity
 * @todo rework filter regexp
 * @todo improve text2links
 * @todo imgcachecallback to use filefolder-methods
 */

class SimpleTemplate {
	private static	$showProtocol = FALSE,
					$suppressLocales = FALSE;

	private		$file,
				$rawContent,
				$dir,
				$contents,
				$filterExpr,
				$locale,
				$filters = array();

	public function __construct($file) {

		$serverBag		= Request::createFromGlobals()->server;
		$this->locale	= Router::getLocaleFromPathInfo();

		$path =
			realpath($serverBag->get('DOCUMENT_ROOT')) .
			(defined('TPL_PATH') ? TPL_PATH : '') .
			(basename($serverBag->get('SCRIPT_NAME'), '.php') !== 'index' ? (basename($serverBag->get('SCRIPT_NAME'), '.php') . DIRECTORY_SEPARATOR) : '');

		if(!is_null($this->locale)) {
			if(file_exists(
				$path .
				$this->locale->getLocaleString() . DIRECTORY_SEPARATOR .
				$file
			)) {
				$this->file = $path . $this->locale->getLocaleString() . DIRECTORY_SEPARATOR . $file;
			}
		}

		if(is_null($this->file)) {
			if (file_exists($path.$file)) {
				$this->file = $path . $file;
			}
			else {
				throw new SimpleTemplateException("Template file '{$path}{$file}' does not exist.", SimpleTemplateException::TEMPLATE_FILE_DOES_NOT_EXIST);
			}
		}

		$this->rawContent = file_get_contents($this->file);
		$this->initFilters();
	}

	public function containsPHP() {
		return preg_match('~<\\?(php)?.*?\\?>~', $this->rawContent);
	}

	public function display() {
		$this->fillBuffer();

		self::parseTemplateLinks	($this->contents);
		self::parseTemplateLocales	($this->contents);
		self::parseImageCaches		($this->contents);

		$this->applyFilters();

		return $this->contents;
	}

	public function assign($var, $value = '') {
		if(is_array($var)) {
			foreach($var as $k => $v) {
				$this->$k = $v;
				return;
			}
		}
		$this->$var = $value;
	}

	/**
	 * adds either predefined or custom filter
	 *
	 * @param string $filter
	 * @param array $custom description of filter if $filter == 'custom'
	 */
	public function addFilter($filter, array $custom = array()) {
		if(function_exists($filter)) {
			array_push($this->filters, $filter);
			return;
		}
		if($filter == 'custom' && count($custom) == 2) {
			array_push($this->filters, $custom);
			return;
		}
		if(in_array($filter, array_keys($this->filterExpr))) {
			array_push($this->filters, $this->filterExpr[$filter]);
			return;
		}
	}

	/**
	 * initalizes simple filters which can be applied to template before display
	 *
	 * a filters are:
	 * first array or string - search expression
	 * second array or string - replacement or
	 * callback method (string only)
	 */
	private function initFilters() {
		$this->filterExpr = array(

			'text2links' =>
				array(
					array(
						'~(^|\s|>|(?:<a [^>]*?>.*?))'.Rex::URI_STRICT.'(<|\s|$)~i',
						'~(<a [^>]*?>.*?|)('.Rex::EMAIL.')([^<]*</a>|)~i',
					),
					array(
						'hrefCallback',
						'encodeEmailCallback'
					),
				),

			'shortenText' =>
				array(
					array(
						'@<(\w+)\s+(.*?)class=(\'|")?([a-z0-9_]*\s*)shortened_(\d+)(.*?)>(?s)(.*?)(?-s)</\s*\1>@i',
					),
					'shortenTextCallback'
				)
			);
	}

	/**
	 * applies filters to template before output
	 */
	private function applyFilters() {
		while(($f = array_shift($this->filters)) !== NULL) {
			if(is_string($f) && function_exists($f)) {
				$this->contents = call_user_func($f, $this->contents);
				continue;
			}
			if(is_string($f[1]) && method_exists($this, $f[1])) {
				$this->contents = preg_replace_callback($f[0], array($this, $f[1]), $this->contents);
				continue;
			}
			foreach($f[0] as $i => $rex) {
				if(method_exists($this, $f[1][$i])) {
					$this->contents = preg_replace_callback($f[0][$i], array($this, $f[1][$i]), $this->contents);
					continue;
				}
				if(function_exists($f[1][$i])) {
					$this->contents = preg_replace_callback($f[0][$i], $f[1][$i], $this->contents);
					continue;
				}
				$this->contents = preg_replace($f[0][$i], $f[1][$i], $this->contents);
			}
		}
	}

	/**
	 * Callback functions for filter go here
	 * @param array $matches
	 */

	private function shortenTextCallback($matches) {
		$prefix = "<{$matches[1]} {$matches[2]}class={$matches[3]}{$matches[4]}shortened{$matches[6]}>";
		$suffix = "</{$matches[1]}>";
		$len = (int) $matches[5];
		$src = $matches[7];

		if(strlen($src) <= $len) { return $prefix.$src.$suffix; }

		$src = strip_tags($src, '<a><strong><em><u><a>');
		$ret = substr($src,0,$len+1);
		return $prefix.substr($ret,0,strrpos($ret,' ')).' &hellip;'.$suffix;
	}

	private function hrefCallback($matches) {
		if(substr($matches[1], 0, 2) == '<a') {
			return $matches[0];
		}

		return	"{$matches[1]}<a class='link_http' href='{$matches[2]}{$matches[3]}{$matches[6]}'>".
				(self::$showProtocol ? $matches[2] : '')."{$matches[3]}{$matches[6]}</a>{$matches[9]}";
	}

	private function encodeEmailCallback($matches) {
		if($matches[1] !== '' || $matches[5] !== '') {
			return $matches[0];
		}

		$pref = 'mailto:';
		$text = '';
		$href = '';
		$encoding = strtoupper($GLOBALS['config']->site->default_encoding);

		$len = strlen($pref);

		for($i = 0; $i < $len; ++$i) {
			$href .= rand(0,1) ? '&#x'.dechex(ord($pref[$i])).';' : '&#'.ord($pref[$i]).';';
		}

		$len = mb_strlen($matches[2], $encoding);

		for($i = 0; $i < $len; ++$i) {
			$t = mb_substr($matches[2], $i, 1, $encoding);
			if(ord($t) > 127) {
				$text .= $t;
			}
			else {
				$text .= rand(0,1) ? '&#x'.dechex(ord($t)).';' : '&#'.ord($t).';';
			}
		}
		$href .= $text;

		return "<a href='$href'>$text</a>";
	}

	/**
	 * fetches template file via include
	 * immediate output supressed by output buffering
	 */
	private function fillBuffer() {
		$tpl = $this;
		ob_start();
		eval("?>{$this->rawContent}");
		$this->contents = ob_get_contents();
		ob_end_clean();
	}

	/**
	 * replaces cropped, resized, greyscaled etc. images with cached resources
	 * e.g.	<img src="pic.png#crop 1|resize 0 30" ...> (in this case the 0 in the resize transformation means "auto")
	 * 		<img src="pic.png" width="20" height="10" ...>
	 * 		<img src="pic.png" style="width:200px; ... height: 100px; ..." ...>
	 * 		< style="... url(pic.png#crop 1|resize 0 30) ...">
	 *
	 * @param string $text parsed text
	 */
	public static function parseImageCaches(&$text) {
		$text = preg_replace_callback(
			array(
				'~<img(.*?)\s+src=("|\')(.*?)#([\w\s\.\|]+)\2(.*?)>~i',
				'~<img(.*?)\s+src=("|\')(.*?)\2.*?(width|height)=("|\')(\d+)\5.*?(width|height)=("|\')(\d+)\8.*?>~i',
				'~<img(.*?)\s+src=("|\')(.*?)\2.*?style=("|\').*?(width|height):\s*(\d+)px;.*?(width|height):\s*(\d+)px.*?\4.*?>~i',
				'~url\s*\(("|\'|)(.*?)#([\w\s\.\|]+)\1\)~i'
			),
			__CLASS__.'::parseCallbackCachedImages',
			$text
		);
	}

	private static function parseCallbackCachedImages($matches) {

		// <img src="..." style="width: ...; height: ...">
		if(count($matches) == 9) {
			// $matches[3] - File
			// $matches[5] - "width"/"height"
			// $matches[6] - dimension 1
			// $matches[7] - "width"/"height"
			// $matches[8] - dimension 2

			if($matches[5] === $matches[7]) {
				return $matches[0];
			}

			$pi			= pathinfo($matches[3]);
			$actions	= $matches[5] == 'width' ? "resize {$matches[6]} {$matches[8]}" : "resize {$matches[8]} {$matches[6]}";
		}

		// <img src="..." width="..." height="...">
		else if(count($matches) == 10) {
			// $matches[3] - File
			// $matches[4] - "width"/"height"
			// $matches[6] - dimension 1
			// $matches[7] - "width"/"height"
			// $matches[9] - dimension 2

			if(strtolower($matches[4]) === strtolower($matches[7])) {
				return $matches[0];
			}

			$pi			= pathinfo($matches[3]);
			$actions	= strtolower($matches[4]) == 'width' ? "resize {$matches[6]} {$matches[9]}" : "resize {$matches[9]} {$matches[6]}";
		}

		// url(...#...)
		else if (count($matches) == 4) {
			// $matches[2] - File
			// $matches[3] - Modifiers

			$pi			= pathinfo($matches[2]);
			$actions	= $matches[3];
		}

		// <img src="...#...">
		else {
			// $matches[3] - File
			// $matches[4] - Modifiers

			$pi			= pathinfo($matches[3]);
			$actions	= $matches[4];
		}

		$pi['extension'] = isset($pi['extension']) ? ".{$pi['extension']}" : '';

		$dest	= $pi['dirname'].DIRECTORY_SEPARATOR.FilesystemFolder::CACHE_PATH.DIRECTORY_SEPARATOR."{$pi['filename']}{$pi['extension']}@$actions{$pi['extension']}";
		$path	= rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($dest, DIRECTORY_SEPARATOR);

		if(!file_exists($path)) {
			$cachePath	= rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($pi['dirname'],DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.FilesystemFolder::CACHE_PATH;

			if(!file_exists($cachePath)) {
				if(!@mkdir($cachePath)) {
					throw new SimpleTemplateException("Failed to create cache folder $cachePath");
				}
				chmod($cachePath, 0777);
			}

			// create cachefile
			$actions	= explode('|', $actions);
			$imgEdit	= new ImageModifier(rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$pi['dirname'].DIRECTORY_SEPARATOR.$pi['basename']);

			foreach($actions as $a) {
				$params = preg_split('~\s+~', $a);

				$method = array_shift($params);

				if(method_exists('vxPHP\\Image\\ImageModifier', $method)) {
					call_user_func_array(array($imgEdit, $method), $params);
				}
			}
			$imgEdit->export($path);
		}

		// @TODO this _assumes_ that $matches[3] occurs only once

		if(count($matches) >= 9) {
			return str_replace($matches[3], $dest, $matches[0]);
		}
		if(count($matches) == 6) {
			return "<img{$matches[1]} src={$matches[2]}$dest{$matches[2]}{$matches[5]}>";
		}
		else {
			return "url({$matches[1]}$dest{$matches[1]})";
		}
	}

	public static function parseVideoThumbs(&$text) {
		$text = preg_replace_callback(
			'~<a\s+(.*?)href=("|\')(.*?)\.(avi|flv|mov)#([\w\s\.\/]+)\2(.*?)>(.*?)</a>~is',
			__CLASS__.'::parseCallbackVideoThumb',
			$text
		);
	}

	private static function parseCallbackVideoThumb($matches) {
		// $matches[1] leading attributes
		// $matches[2] quote char
		// $matches[3] filename
		// $matches[4] extension
		// $matches[5] action(s)
		// $matches[6] trailing attributes
		// $matches[7] contained text node

		$action		= preg_split('~\s+~', $matches[5]);

		if(
			count($action) < 4 ||
			$action[0] !== 'thumb' ||
			!is_numeric($action[1]) ||
			!is_numeric($action[2]) ||
			!is_numeric($action[3])
		) {
			return $matches[0];
		}

		$pi			= pathinfo("{$matches[3]}.{$matches[4]}");
		$filePath	= FilesystemFolder::getInstance(rtrim($_SERVER['DOCUMENT_ROOT'], '/').'/'.$pi['dirname']);

		$src		= "{$pi['filename']}.{$pi['extension']}";
		$dest		= "$src@{$matches[5]}.jpg";

		if(!file_exists($filePath->getPath().$src)) {
			return $matches[0];
		}

		$seconds	= (float) $action[1];
		$width		= (int) $action[2];
		$height		= (int) $action[3];

		if(!($cachePath = $filePath->getCachePath())) {
			$cachePath = $filePath->createCache();
		}

		if(!file_exists($cachePath.$dest)) {
			exec("ffmpeg -i \"{$filePath->getPath()}$src\" -vframes 1 -an -s {$width}x{$height} -ss $seconds \"$cachePath$dest\"");

			// ffmpeg failed
			if(!file_exists($cachePath.$dest)) {
				return $matches[0];
			}

			var_dump(IMG_SITE_PATH.$action[4]);
			if(!empty($action[4]) && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR).IMG_SITE_PATH.$action[4])) {
				$iE = new ImageModifier($cachePath.$dest);
				$iE->watermark(rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR).IMG_SITE_PATH.$action[4]);
				$iE->export($cachePath.$dest);
			}
		}

		return "<a {$matches[1]}href={$matches[2]}{$matches[3]}.{$matches[4]}{$matches[2]}{$matches[6]}><img src='".$filePath->getRelativePath().FilesystemFolder::CACHE_PATH.'/'.$dest."' alt=''></a>";
	}

	/**
	 * parses local page links and images
	 * <a href="$foo&bar"> becomes <a href="{ROOT_DOCUMENT}?page=foo&bar">
	 * <img src="$foo.gif"> becomes <img src="{IMG_SITE_PATH}/foo.gif">
	 *
	 * @param string $text parsed text
	 */
	public static function parseTemplateLinks(&$text) {
		if(empty($text)) {
			return;
		}
		if($GLOBALS['config']->site->use_nice_uris) {
			$text = preg_replace_callback(
				'~<a(.*?)\s+href=("|\')\$([a-z0-9_./-]+)(.*?)\2(.*?)>~i',
				__CLASS__.'::parseCallbackNiceA',
				$text);
		}
		else {
			$text = preg_replace_callback(
				'~<a(.*?)\s+href=("|\')\$([a-z0-9_./-]+)(.*?)\2(.*?)>~i',
				__CLASS__.'::parseCallbackA',
				$text);
		}
		$text = preg_replace_callback(
			'~<img(.*?)\s+src=("|\')\$([^"\']+)\2(.*?)>~i',
			__CLASS__.'::parseCallbackImg',
			$text);
	}

	private static function parseCallbackNiceA($matches) {
		static $doc = NULL;
		if(empty($doc)) {
			$doc = $GLOBALS['config']->getDocument();
		}

		$matches[4] = html_entity_decode($matches[4]);

		$uri = NiceURI::toNice("/{$doc}?page={$matches[3]}{$matches[4]}");

		return "<a{$matches[1]} href={$matches[2]}$uri{$matches[2]}{$matches[5]}>";
	}

	private static function parseCallbackA($matches) {
		static $doc = NULL;
		if(empty($doc)) {
			$doc = $GLOBALS['config']->getDocument();
		}
		return "<a{$matches[1]} href={$matches[2]}{$doc}?page={$matches[3]}{$matches[4]}{$matches[2]}{$matches[5]}>";
	}
	private static function parseCallbackImg($matches) {
		return "<img{$matches[1]} src={$matches[2]}".IMG_SITE_PATH."{$matches[3]}{$matches[2]}{$matches[4]}>";
	}

	/**
	 * translates single word or phrase
	 * @param string $word
	 * @return string translated word
	 */
	public static function translatePhrase($phrase) {
		$locale = isset($GLOBALS['config']->site->current_locale) ? $GLOBALS['config']->site->current_locale : '';
		if(empty($locale)) {
			return '';
		}
		self::getPhrases($locale);
		if(isset($GLOBALS['phrases'][$GLOBALS['config']->site->current_locale][$phrase])) {
			return $GLOBALS['phrases'][$GLOBALS['config']->site->current_locale][$phrase];
		}

		self::storePhrase($phrase);
	}

	/**
	 * stores a default phrase in the locales file(s)
	 *
	 *  @param string $phrase phrase to store
	 *  @return "translated" phrase
	 */
	private static function storePhrase($phrase, $key = NULL) {
		$path = defined('LOCALE_PATH') ? LOCALE_PATH : '';
		$locales = $GLOBALS['config']->site->locales;
		if(!isset($key)) {
			$key = $phrase;
			$phrase = ucfirst(str_replace('_', ' ', $phrase));
		}

		foreach($locales as $l) {
			if(!isset($GLOBALS['phrases'][$l][$key])) {
				$GLOBALS['phrases'][$l][$key] = $phrase;
				$handle = @fopen($path.$l.'.phrases', 'a');
				if($handle !== FALSE) {
					fwrite($handle, sprintf("\n%s = '%s'", $key, $phrase));
					fclose($handle);
				}
				else {
					$GLOBALS['phrases'][$l][$key] = NULL;
				}
			}
		}

		return $phrase;
	}

	/**
	 * parses local page locale expressions
	 * {!word} becomes lookup value of locale.terms
	 *
	 * @param string $text parsed text
	 */
	public static function parseTemplateLocales(&$text) {
		if(empty($text) || self::$suppressLocales) {
			return;
		}

		$locale = isset($GLOBALS['config']->site->current_locale) ? $GLOBALS['config']->site->current_locale : '';
		if(empty($locale)) {
			$text = preg_replace(array(
				'@\{![a-z0-9_]+\}@i',
				'@\{![a-z0-9_]+:(.*?)\}@i'), array('', '$1'), $text);
			return;
		}

		self::getPhrases($locale);
		$text = preg_replace_callback('@\{!([a-z0-9_]+)(:(.*?))?\}@i', __CLASS__.'::translatePhraseCallback', $text);
	}

	private static function translatePhraseCallback($matches) {
		if(
			!empty($GLOBALS['phrases'][$GLOBALS['config']->site->current_locale][$matches[1]])) {
				return $GLOBALS['phrases'][$GLOBALS['config']->site->current_locale][$matches[1]];
		}
		if(isset($matches[3])) {
			return self::storePhrase($matches[3], $matches[1]);
		}
		else {
			return self::storePhrase($matches[1]);
		}
	}

	private static function getPhrases($locale) {
		if(
			!isset($GLOBALS['phrases'][$locale]) &&
			file_exists((defined('LOCALE_PATH') ? LOCALE_PATH : '').$locale.'.phrases')
		) {
			$GLOBALS['phrases'][$locale] = parse_ini_file(LOCALE_PATH.$locale.'.phrases');
		}
	}

	/**
	 * crate image tag
	 * @param string src source file
	 * @param string alt alt text
	 * @param string title title text
	 * @param string class css class
	 * @param boolean timestamp add source "parameter" to force refresh
	 */
	public static function img($src, $alt = NULL, $title = NULL, $class = NULL, $timestamp = FALSE) {
		if(empty($alt)) {
			$alt = explode('.', basename($alt));
			array_pop($alt);
			$alt = implode('.', $alt);
		}
		$html = '<img src="'.$src.($timestamp ? '?'.filemtime($src) : '').'" alt="'.$alt.'"';
		$html .= empty($title) ? '' : ' title="'.$title.'"';
		$html .= empty($class) ? '>' : ' class="'.$class.'">';
		return $html;
	}

	/**
	 * create anchor tag
	 * @param string link URI or relative link
	 * @param string text link test
	 * @param string img image name used within link
	 * @param string class css class
	 * @param string miscstr additional string with attributes or handlers
	 * @param string $counted add code for counting clicks on link
	 */
	public static function a($link, $text = '', $img = '', $class = FALSE, $miscstr = FALSE, $counted = FALSE) {
		if (empty($link)) {
			return FALSE;
		}

		$mail	= self::checkMail($link);
		$ext	= !$mail ? self::checkExternal($link) : TRUE;

		if($mail) {
			$enc = 'mailto:';
			$len = strlen($link);
			for($i = 0; $i < $len; $i++) {
				$enc .= rand(0,1) ? '&#x'.dechex(ord($link[$i])).';' : '&#'.ord($link[$i]).';';
			}
			$link = $enc;
		}

		else {
			if(!$ext && $GLOBALS['config']->site->use_nice_uris) {
				$link = NiceURI::toNice($link);
			}
			else if($ext && $counted) {
				$link = 'countclick.php?uri='.urlencode($link).(isset($_SERVER['REQUEST_URI']) ? '&referer='.urlencode($_SERVER['REQUEST_URI']) : '');
			}
			$link = htmlspecialchars($link);
		}

		$text = ($text == '' && $img == '') ? preg_replace('~^\s*[a-z]+:(//)?~i', '', $link) : $text;

		$class = $class ? array($class) : array();
		if(self::checkExternal($link)) {
			$class[] = 'external';
		}

		$html = array(
			'<a',
			!empty($class) ? ' class="'.implode(' ', $class).'"' : '',
			" href='$link'",
			$miscstr ? " $miscstr>" : '>',
			$img != '' ? "<img src='$img' alt='$text'>" : $text,
			'</a>'
		);
		return implode('', $html);
	}

	public static function checkExternal($link) {
		return preg_match('/^(\s*(ftp:\/\/|http(s?):\/\/))/i', $link);
	}

	public static function checkMail($link) {
		return preg_match('!^'.Rex::EMAIL.'$!', $link);
	}

	public static function checkUri($link) {
		return preg_match('!^'.Rex::URI.'$!', $link);
	}

	public static function highlightText($text, $keyword) {
		return str_replace($keyword, "<span class='highlight'>$keyword</span>", $text);
	}

	public static function shortenText($text, $len) {
		$src = strip_tags($text);
		if(strlen($src) <= $len) { return $text; }
		$ret = substr($src,0,$len+1);
		return substr($ret,0,strrpos($ret,' ')).' &hellip;';
	}

	private function getPath() {
		$path = rtrim($_SERVER['DOCUMENT_ROOT'], '/');

		if(defined('TPL_PATH')) {
			$path .=  TPL_PATH;
		}
		$subpath = basename($GLOBALS['config']->getDocument(), '.php');
		$path .= file_exists($path.$subpath) ? "$subpath/" : '';
		return $path;
	}
}
?>

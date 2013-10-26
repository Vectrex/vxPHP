<?php

namespace vxPHP\Template;

use vxPHP\Template\Exception\SimpleTemplateException;
use vxPHP\Util\Rex;

use vxPHP\Http\Router;
use vxPHP\Http\Request;

use vxPHP\Application\Application;
use vxPHP\Webpage\NiceURI;
use vxPHP\Template\Filter\SimpleTemplateFilterInterface;
use vxPHP\Template\Filter\ImageCache;
use vxPHP\Template\Filter\AnchorHref;
use vxPHP\Template\Filter\LocalizedPhrases;

/**
 * A simple template system
 *
 * @author Gregor Kofler
 * @version 1.1.0 2013-10-27
 *
 */

class SimpleTemplate {

	private		$path,
				$file,
				$rawContent,
				$dir,
				$contents,
				$locale,
				$filters = array(),
				$ignoreLocales;

	public function __construct($file) {

		$request		= Request::createFromGlobals();
		$serverBag		= $request->server;
		$this->locale	= Router::getLocaleFromPathInfo();
		$this->file		= $file;

		$this->path		= realpath($serverBag->get('DOCUMENT_ROOT')) . (defined('TPL_PATH') ? TPL_PATH : '');

		if (!file_exists($this->path . $this->file)) {
			throw new SimpleTemplateException("Template file '{$this->path}{$this->file}' does not exist.", SimpleTemplateException::TEMPLATE_FILE_DOES_NOT_EXIST);
		}

		$this->rawContent = file_get_contents($this->path . $this->file);
	}

	/**
	 * check whether template file contains any PHP escape characters
	 *
	 * @return boolean
	 */
	public function containsPHP() {
		return 1 === preg_match('~<\\?(php)?.*?\\?>~', $this->rawContent);
	}

	/**
	 * output parsed template
	 *
	 * @return string
	 */
	public function display() {

		$this->extend();

		$this->fillBuffer();

		$this->addFilter(new AnchorHref());
		$this->addFilter(new ImageCache());

		if(!$this->ignoreLocales) {
			$this->addFilter(new LocalizedPhrases());
		}

		$this->applyFilters();

		return $this->contents;
	}

	/**
	 * assign value to variable, which is the available within template
	 *
	 * @param string $var
	 * @param mixed $value
	 */
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
	 * appends filter to filter queue
	 *
	 * @param SimpleTemplateFilterInterface $filter
	 */
	public function addFilter(SimpleTemplateFilterInterface $filter) {

		array_push($this->filters, $filter);

	}

	/**
	 * include another template file
	 * does only path handling
	 *
	 * @param string $templateFile
	 */
	private function includeFile($templateFile) {

		$tpl = $this;
		eval('?>' . file_get_contents($this->path . $templateFile));

	}

	/**
	 * include controller output
	 *
	 * @param Controller $controller
	 */
	private function includeController($controller) {

	}

	/**
	 * allow extension of a parent template with current template
	 *
	 * searches in current rawContent for
	 * <!-- { extend: parent_template.php @ content_block } -->
	 * and in template to extend for
	 * <!-- { block: content_block } -->
	 *
	 * current rawContent is then replaced by parent rawContent with current rawContent filled in
	 *
	 * @throws SimpleTemplateException
	 */
	private function extend() {

		$extendRegExp = '~<!--\s*\{\s*extend:\s*([\w./-]+)\s*@\s*([\w-]+)\s*\}\s*-->~';

		if(preg_match($extendRegExp, $this->rawContent, $matches)) {

			$blockRegExp = '~<!--\s*\{\s*block\s*:\s*' . $matches[2] . '\s*\}\s*-->~';

			$extendedContent = file_get_contents($this->path . $matches[1]);

			if(preg_match($blockRegExp, $extendedContent)) {

				$this->rawContent = preg_replace(
					$blockRegExp,
					preg_replace(
						$extendRegExp,
						'',
						$this->rawContent
					),
					$extendedContent
				);

			}

			else {
				throw new SimpleTemplateException("Could not extend with '{$matches[1]}' at '{$matches[2]}'.", SimpleTemplateException::TEMPLATE_INVALID_NESTING);
			}
		}
	}

	/**
	 * applies all stacked filters to template before output
	 */
	private function applyFilters() {

		foreach($this->filters as $f) {
			$f->apply($this->contents);
		}

	}

	/**
	 * fetches template file and evals content
	 * immediate output supressed by output buffering
	 */
	private function fillBuffer() {
		$tpl = $this;
		ob_start();

		eval('?>' . $this->rawContent);
		$this->contents = ob_get_contents();
		ob_end_clean();
	}

	/**
	 * crate image tag
	 *
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
	 *
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
			if(!$ext && Application::getInstance()->getConfig()->site->use_nice_uris) {
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
		$subpath = basename(Application::getInstance()->getConfig()->getDocument(), '.php');
		$path .= file_exists($path.$subpath) ? "$subpath/" : '';
		return $path;
	}
}

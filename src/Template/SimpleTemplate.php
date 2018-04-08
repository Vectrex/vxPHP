<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Template;

use vxPHP\Template\Exception\SimpleTemplateException;
use vxPHP\Util\Rex;
use vxPHP\Application\Application;
use vxPHP\Webpage\NiceURI;
use vxPHP\Template\Filter\SimpleTemplateFilterInterface;
use vxPHP\Template\Filter\ImageCache;
use vxPHP\Template\Filter\AnchorHref;
use vxPHP\Template\Filter\AssetsPath;
use vxPHP\Template\Filter\LocalizedPhrases;
use vxPHP\Application\Locale\Locale;
use vxPHP\Controller\Controller;
use vxPHP\Application\Exception\ConfigException;

/**
 * A simple template system
 *
 * @author Gregor Kofler
 * @version 1.6.3 2018-04-08
 *
 */

class SimpleTemplate {

				/**
				 * @var string
				 * 
				 * absolute path to template file
				 */
	private		$path;

				/**
				 * @var string
				 * 
				 * the unprocessed template string
				 */
	private		$rawContents;
	
				/**
				 * @var string
				 * 
				 * the processed template string
				 */
	private		$contents;

				/**
				 * @var Locale
				 */
	private		$locale;
	
				/**
				 * store for added custom filters with addFilter()
				 * 
				 * @var array
				 */
	private		$filters = [];
	
				/**
				 * keeps instances of pre-configured filters
				 * will be applied before any added custom filters
				 * 
				 * @var array $configuredFilters
				 */
	private static $configuredFilters;

				/**
				 * @var boolean
				 */
	private		$ignoreLocales;

				/**
				 * name of a parent template found in <!-- extend: ... -->
				 * 
				 * @var string
				 */
	private		$parentTemplateFilename;

				/**
				 * the regular expression employed to search for an "extend@..." directive
				 * 
				 * @var string
				 */
	private		$extendRex = '~<!--\s*\{\s*extend:\s*([\w./-]+)\s*@\s*([\w-]+)\s*\}\s*-->~';

    /**
     * initialize template based on $file
     * if $file is omitted the content can be set with setRawContents() later
     *
     * @param string $file
     * @throws SimpleTemplateException
     */
	public function __construct($file = null) {

		$application = Application::getInstance();

		if($file) {
			$this->path = $application->getRootPath() . (defined('TPL_PATH') ? str_replace('/', DIRECTORY_SEPARATOR, ltrim(TPL_PATH, '/')) : '');

			if (!file_exists($this->path . $file)) {
				throw new SimpleTemplateException(sprintf("Template file '%s' does not exist.", $this->path . $file), SimpleTemplateException::TEMPLATE_FILE_DOES_NOT_EXIST);
			}

			$this->setRawContents(file_get_contents($this->path . $file));

		}
		
		$this->locale = $application->getCurrentLocale();
	}

    /**
     * static method to allow method chaining
     *
     * @param string $file
     * @return SimpleTemplate
     */
	public static function create($file = null) {

		return new static($file);

	}

	/**
	 * set or overwrite the raw contents of the template
	 * 
	 * @param string $contents
	 * @return SimpleTemplate
	 */
	public function setRawContents($contents) {

		$this->rawContents = (string) $contents;
		return $this;

	}
	
	/**
	 * check whether template file contains PHP code
	 * does this by searching for opening PHP tags:
	 * <? <?= <?php
	 * no checks for ASP notation are applied 
	 *
	 * @return boolean
	 */
	public function containsPHP() {

		return 1 === preg_match('~\<\?(?:=|php\s|\s)~', $this->rawContents);

	}

	/**
	 * return the plain file content of template file
	 *
	 * @return string
	 */
	public function getrawContents() {

		return $this->rawContents;

	}

	
	public function getParentTemplateFilename() {

		if(empty($this->parentTemplateFilename)) {
		
			if(preg_match($this->extendRex, $this->rawContents, $matches)) {

				$this->parentTemplateFilename = $matches[1];

			}
		}

		return $this->parentTemplateFilename;
		
	}

    /**
     * explicitly insert template at $blockName position
     *
     * @param SimpleTemplate $childTemplate
     * @param string $blockName
     * @return SimpleTemplate
     * @throws SimpleTemplateException
     */
	public function insertTemplateAt(SimpleTemplate $childTemplate, $blockName) {

		$blockRegExp = '~<!--\s*\{\s*block\s*:\s*' . $blockName . '\s*\}\s*-->~';

		if(preg_match($blockRegExp, $this->rawContents)) {

			$this->rawContents = preg_replace($blockRegExp, $childTemplate->getRawContents(), $this->rawContents);

		}

		else {
			throw new SimpleTemplateException(sprintf("Could not insert child template at '%s'.", $blockName), SimpleTemplateException::TEMPLATE_INVALID_NESTING);
		}

		return $this;
	}

	/**
	 * assign value to variable, which is then available within template
	 *
	 * @param string $var
	 * @param mixed $value
	 * @return SimpleTemplate
	 */
	public function assign($var, $value = '') {

		if(is_array($var)) {
			foreach($var as $k => $v) {
				$this->$k = $v;
			}

			return $this;
		}

		$this->$var = $value;

		return $this;
	}

	/**
	 * appends filter to filter queue
	 *
	 * @param SimpleTemplateFilterInterface $filter
	 * @return SimpleTemplate
	 */
	public function addFilter(SimpleTemplateFilterInterface $filter) {

		array_push($this->filters, $filter);

		return $this;

	}

    /**
     * output parsed template
     *
     * @return string
     * @throws SimpleTemplateException
     */
	public function display() {

		$this->extend();

		$this->fillBuffer();

		// check whether pre-configured filters are already in place
		
		if(is_null(self::$configuredFilters)) {

			// add default filters

			self::$configuredFilters = array(
				new AnchorHref(),
				new ImageCache(),
				new AssetsPath()
			);

			if(!$this->ignoreLocales) {
				self::$configuredFilters[] = new LocalizedPhrases();
			}

			// add configured filters

			if($templatingConfig = Application::getInstance()->getConfig()->templating) {

				foreach($templatingConfig->filters as $id => $filter) {

					// load class file

					$instance = new $filter['class']();

					// check whether instance implements FilterInterface

					if(!$instance instanceof SimpleTemplateFilterInterface) {
						throw new SimpleTemplateException(sprintf("Template filter '%s' (class %s) does not implement the SimpleTemplateFilterInterface.", $id, $filter['class']));
					}

					self::$configuredFilters[] = $instance;

				}
			}
		}


		$this->applyFilters();

		return $this->contents;
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
     * $controllerPath is [path/to/controller/]name_of_controller
     * additional arguments can be passed on to the controller constructor
     *
     * @param string $controllerPath
     * @param string $methodName
     * @param array $constructorArguments
     *
     * @return string
     * @throws ConfigException
     */
	private function includeControllerResponse($controllerPath, $methodName = null, array $constructorArguments = null) {

		$namespaces = explode('\\', ltrim(str_replace('/', '\\', $controllerPath), '/\\'));
		
		if(count($namespaces) && $namespaces[0]) {
			$controller = '\\Controller\\'. implode('\\', array_map('ucfirst', $namespaces)) . 'Controller';
		}
		
		else {
			throw new ConfigException(sprintf("Controller string '%s' cannot be parsed.", $controllerPath));
		}
		

		// get instance and set method which will be called in render() method of controller

		$controllerClass = Application::getInstance()->getApplicationNamespace() . $controller;
		
		if(!$constructorArguments) {
			
			/**
			 * @var Controller
			 */
			$instance = new $controllerClass();
			
		}

		else {

			$instance = new $controllerClass(...$constructorArguments);
				
		}

		if($methodName) {

			return $instance->setExecutedMethod($methodName)->render();

		}
		else {

			return $instance->setExecutedMethod('execute')->render();

		}

	}

	/**
	 * allow extension of a parent template with current template
	 *
	 * searches in current rawContents for
	 * <!-- { extend: parent_template.php @ content_block } -->
	 * and in template to extend for
	 * <!-- { block: content_block } -->
	 *
	 * current rawContents is then replaced by parent rawContents with current rawContents filled in
	 *
	 * @throws SimpleTemplateException
	 */
	private function extend() {

		if(preg_match($this->extendRex, $this->rawContents, $matches)) {

			$blockRegExp = '~<!--\s*\{\s*block\s*:\s*' . $matches[2] . '\s*\}\s*-->~';

			$extendedContent = file_get_contents($this->path . $matches[1]);

			if(preg_match($blockRegExp, $extendedContent)) {

				$this->rawContents = preg_replace(
					$blockRegExp,
					preg_replace(
						$this->extendRex,
						'',
						$this->rawContents
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

		// handle default and pre-configured filters first

		foreach(self::$configuredFilters as $f) {
			$f->apply($this->contents);
		}

		// handle added custom filters last

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

		eval('?>' . $this->rawContents);
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
     * @return string
     */
	public static function img($src, $alt = null, $title = null, $class = null, $timestamp = false) {
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
     * @param string|bool class css class
     * @param string|bool miscstr additional string with attributes or handlers
     * @return bool|string
     */
	public static function a($link, $text = '', $img = '', $class = false, $miscstr = false) {

		if (empty($link)) {
			return false;
		}

		$mail	= self::checkMail($link);
		$ext	= !$mail ? self::checkExternal($link) : true;

		if($mail) {
			$enc = 'mailto:';
			$len = strlen($link);
			for($i = 0; $i < $len; $i++) {
				$enc .= rand(0,1) ? '&#x'.dechex(ord($link[$i])).';' : '&#'.ord($link[$i]).';';
			}
			$link = $enc;
		}

		else {
			if(!$ext && Application::getInstance()->hasNiceUris()) {
				$link = NiceURI::toNice($link);
			}

			$link = htmlspecialchars($link);
		}

		$text = ($text == '' && $img == '') ? preg_replace('~^\s*[a-z]+:(//)?~i', '', $link) : $text;

		$class = $class ? [$class] : [];
		if(self::checkExternal($link)) {
			$class[] = 'external';
		}

		$html = [
			'<a',
			!empty($class) ? ' class="'.implode(' ', $class).'"' : '',
			" href='$link'",
			$miscstr ? " $miscstr>" : '>',
			$img != '' ? "<img src='$img' alt='$text'>" : $text,
			'</a>'
		];
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
}

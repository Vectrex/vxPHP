<?php

namespace vxPHP\Template;

use vxPHP\Template\Exception\SimpleTemplateException;
use vxPHP\Util\Rex;

use vxPHP\Routing\Router;
use vxPHP\Http\Request;

use vxPHP\Application\Application;
use vxPHP\Webpage\NiceURI;
use vxPHP\Template\Filter\SimpleTemplateFilterInterface;
use vxPHP\Template\Filter\ImageCache;
use vxPHP\Template\Filter\AnchorHref;
use vxPHP\Template\Filter\AssetsPath;
use vxPHP\Template\Filter\LocalizedPhrases;
use vxPHP\Application\Locale\Locale;
use vxPHP\Controller\Controller;
use vxPHP\Template\Filter\SimpleTemplateFilter;

/**
 * A simple template system
 *
 * @author Gregor Kofler
 * @version 1.5.0 2015-10-11
 *
 */

class SimpleTemplate {

	private		$path,
				$file,
				$rawContent,
				$dir,
				$contents;

				/**
				 * @var Locale
				 */
	private		$locale;
	
				/**
				 * store for added custom filters with addFilter()
				 * 
				 * @var array
				 */
	private		$filters = array();
	
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

	private		$extendRex = '~<!--\s*\{\s*extend:\s*([\w./-]+)\s*@\s*([\w-]+)\s*\}\s*-->~';

	/**
	 * initialize template based on $file
	 *
	 * @param string $file
	 */
	public function __construct($file) {

		$application	= Application::getInstance();

		$this->locale	= $application->getCurrentLocale();
		$this->file		= $file;

		$this->path		= $application->getRootPath() . (defined('TPL_PATH') ? str_replace('/', DIRECTORY_SEPARATOR, ltrim(TPL_PATH, '/')) : '');

		if (!file_exists($this->path . $this->file)) {
			throw new SimpleTemplateException("Template file '{$this->path}{$this->file}' does not exist.", SimpleTemplateException::TEMPLATE_FILE_DOES_NOT_EXIST);
		}

		$this->rawContent = file_get_contents($this->path . $this->file);
	}

	/**
	 * static method to allow method chaining
	 *
	 * @param string $file
	 */
	public static function create($file) {

		return new static($file);

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
	 * return the plain file content of template file
	 *
	 * @return string
	 */
	public function getRawContent() {
 		return $this->rawContent;
	}

	
	public function getParentTemplateFilename() {

		if(empty($this->parentTemplateFilename)) {
		
			if(preg_match($this->extendRex, $this->rawContent, $matches)) {

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
	 */
	public function insertTemplateAt(SimpleTemplate $childTemplate, $blockName) {

		$blockRegExp = '~<!--\s*\{\s*block\s*:\s*' . $blockName . '\s*\}\s*-->~';

		if(preg_match($blockRegExp, $this->rawContent)) {

			$this->rawContent = preg_replace($blockRegExp, $childTemplate->getRawContent(), $this->rawContent);

		}

		else {
			throw new SimpleTemplateException("Could not insert child template at '$blockName'.", SimpleTemplateException::TEMPLATE_INVALID_NESTING);
		}

		return $this;
	}

	/**
	 * assign value to variable, which is the available within template
	 *
	 * @param string $var
	 * @param mixed $value
	 * @return SimpleTemplate
	 */
	public function assign($var, $value = '') {

		if(is_array($var)) {
			foreach($var as $k => $v) {
				$this->$k = $v;
				return;
			}
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

				$rootPath = Application::getInstance()->getRootPath();

				foreach($templatingConfig->filters as $id => $filter) {

					// load class file

					$class	= $filter['class'];
					$file	= $rootPath . 'src/' . $filter['classPath'] . $class . '.php';

					if(!file_exists($file)) {
						throw new SimpleTemplateException(sprintf("Class file '%s' for templating filter '%s' not found.", $file, $id));
					}

					require $file;

					$instance = new $class;

					// check whether instance implements FilterInterface

					if(!$instance instanceof SimpleTemplateFilterInterface) {
						throw new SimpleTemplateException(sprintf("Template filter '%s' (class %s) does not implement the SimpleTemplateFilterInterface.", $id, $class));
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
	 */
	private function includeControllerResponse($controllerPath, $methodName = NULL, array $constructorArguments = NULL) {

		$classPath		= explode('/', $controllerPath);
		$controllerName	= array_pop($classPath);
		
		// append 'Controller' to controller name

		$className	= ucfirst($controllerName) . 'Controller';

		// build physical path to controller and include controller

		if(count($classPath)) {
			$classPath = implode(DIRECTORY_SEPARATOR, $classPath) . DIRECTORY_SEPARATOR;
		}
		else {
			$classPath = '';
		}
		
		require_once Application::getInstance()->getControllerPath() . $classPath . $className . '.php';

		// get instance and set method which will be called in render() method of controller

		if(!$constructorArguments) {
			
			// no additional arguments required to pass on

			$instance = new $className();
		}

		else {

			// use reflection to pass on additional constructor arguments
			
			$reflector	= new \ReflectionClass($className);
			$instance	= $reflector->newInstanceArgs($constructorArguments);

		}

		if(!empty($methodName)) {
			return $instance->setExecutedMethod($methodName)->render();
		}
		
		else {
			return $instance->setExecutedMethod('execute')->render();
		}

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

		if(preg_match($this->extendRex, $this->rawContent, $matches)) {

			$blockRegExp = '~<!--\s*\{\s*block\s*:\s*' . $matches[2] . '\s*\}\s*-->~';

			$extendedContent = file_get_contents($this->path . $matches[1]);

			if(preg_match($blockRegExp, $extendedContent)) {

				$this->rawContent = preg_replace(
					$blockRegExp,
					preg_replace(
						$this->extendRex,
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
	public static function a($link, $text = '', $img = '', $class = FALSE, $miscstr = FALSE) {

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
			if(!$ext && Application::getInstance()->hasNiceUris()) {
				$link = NiceURI::toNice($link);
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
}

<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * 
 * PSR-4 autoloader class,
 * taken with minimal modifications from
 * Aura for PHP
 * 
 * https://github.com/auraphp/Aura.Autoload
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 * 
 */
namespace vxPHP\Autoload;

class Psr4 {

	/**
	 * map with file paths for each class
	 * 
	 * @var array
	 */
	protected  $classPaths = [];
	
	/**
	 * debug information populated by loadClass()
	 * 
	 * @var array
	 */
	protected $debug = [];
	
	/**
	 *
	 * map for loaded classes, interfaces, and traits loaded by the autoloader
	 * the keys are class names and the values the file names
	 *
	 * @var array
	 */
	protected $loadedClasses = [];
	
	/**
	 * map of namespace prefixes to base directories
	 *
	 * @var array
	 */
	protected $prefixes = [];
	
	/**
	 * register autoloader with SPL
	 *
	 * @param bool $prepend, prepend to the autoload stack when TRUE
	 * @return void
	 *
	 */
	public function register($prepend = FALSE) {
		spl_autoload_register(
			array($this, 'loadClass'),
			TRUE,
			(bool) $prepend
		);
	}

	/**
	 * unregisters this autoloader from SPL
	 *
	 * @return void
	 */
	public function unregister() {
		spl_autoload_unregister(array($this, 'loadClass'));
	}

	/**
	 * get debugging information array from the last loadClass()
	 * attempt
	 *
	 * @return array
	 */
	public function getDebug() {
		return $this->debug;
	}

	/**
	 * add a base directory for a namespace prefix
	 *
	 * @param string $prefix
	 * @param string|array $baseDirs: one or more base directories for the namespace prefix
	 * @param bool $prepend: if true, prepend base directories to prefix instead of appending them;
	 * this causes them to be searched first rather than last.
	 *
	 * @return void
	 */
	public function addPrefix($prefix, $baseDirs, $prepend = FALSE) {

		$baseDirs	= (array) $baseDirs;
		
		// normalize the namespace prefix

		$prefix		= trim($prefix, '\\') . '\\';

		// initialize the namespace prefix array if needed

		if (! isset($this->prefixes[$prefix])) {
			$this->prefixes[$prefix] = [];
		}

		// normalize each base dir with a trailing separator
		
		foreach ($baseDirs as $ndx => $dir) {
			$baseDirs[$ndx] = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		}

		// prepend or append?

		$this->prefixes[$prefix] = $prepend ? array_merge($baseDirs, $this->prefixes[$prefix]) : $this->prefixes[$prefix] = array_merge($this->prefixes[$prefix], $baseDirs);

	}
	
	/**
	 * set all namespace prefixes and their base directories; overwrites
	 * existing prefixes
	 *
	 * @param array $prefixes: associative array of namespace prefixes and their base directories
	 *
	 * @return void
	 */
	public function setPrefixes(array $prefixes) {
		$this->prefixes = [];
		foreach ($prefixes as $prefix => $baseDir) {
			$this->addPrefix($prefix, $baseDir);
		}
	}
	
	/**
	 * get list of all class name prefixes and their base directories
	 *
	 * @return array
	 */
	public function getPrefixes() {
		return $this->prefixes;
	}
	
	/**
	 * sets explicit file path for explicit class name
	 *
	 * @param string $class
	 * @param string $file
	 *
	 * @return void
	 */
	public function setClassFile($class, $file) {
		$this->classPaths[$class] = $file;
	}
	
	/**
	 * set all file paths for all class names;overwrites all previous mappings
	 *
	 * @param array $classPaths: map with class name (key) and file path (value)
	 *
	 * @return void
	 */
	public function setClassFiles(array $classPaths) {
		$this->classPaths = $classPaths;
	}

	/**
	 * add file paths for class names to existing mappings
	 *
	 * @param array $classPaths:  map with class name (key) and file path (value)
	 *
	 * @return void
	 */
	public function addClassFiles(array $classPaths) {
		$this->classPaths = array_merge($this->classPaths, $classPaths);
	}
	
	/**
	 * get the map of class names (key) to file paths (value)
	 *
	 * @return array
	 */
	public function getClassFiles() {
		return $this->classPaths;
	}
	
	/**
	 *
	 * get list of classes, interfaces, and traits loaded by the autoloader
	 * keys are class names, values are file names
	 *
	 * @return array
	 */
	public function getLoadedClasses() {
		return $this->loadedClasses;
	}
	
	/**
	 * load class file for a class name $class
	 * returns mapped file name on success, or boolean false on failure
	 *
	 * @param string $class: the fully-qualified class name
	 *
	 * @return mixed
	 */
	public function loadClass($class) {

		// reset debug info

		$this->debug = array('Loading ' . $class);
	
		// is class mapped to file

		if (isset($this->classPaths[$class])) {

			$file = $this->classPaths[$class];

			if ($this->requireFile($file)) {
				$this->debug[] = 'Loaded from: ' . $file;
				$this->loadedClasses[$class] = $file;
				return $file;
			}
		}
	
		// no map entry found
		
		$this->debug[] = 'No explicit class file';
	
		$prefix = $class;
	
		// work backwards through the namespace names of the fully-qualified
		// class name to find a mapped file name

		while (FALSE !== ($pos = strrpos($prefix, '\\'))) {
	
			// retain the trailing namespace separator in the prefix

			$prefix = substr($class, 0, $pos + 1);
	
			// the rest is the relative class name

			$relativeClass = substr($class, $pos + 1);
	
			// try to load a mapped file for the prefix and relative class

			$file = $this->loadFile($prefix, $relativeClass);

			if ($file) {
				$this->debug[] = 'Loaded from ' . $prefix . ': ' . $file;
				$this->loadedClasses[$class] = $file;
				return $file;
			}
	
			$prefix = rtrim($prefix, '\\');

		}
	
		// did not find a file for the class

		$this->debug[] = $class . 'not loaded';
		return FALSE;
	}
	
	/**
	 * loads mapped file for a namespace prefix and relative class
	 *
	 * @param string $prefix
	 * @param string $relativeClass
	 *
	 * @return mixed name of mapped file that was loaded or FALSE if file could not be loaded
	 */
	protected function loadFile($prefix, $relativeClass) {

		// any base directories for this namespace prefix?

		if (!isset($this->prefixes[$prefix])) {
			$this->debug[] = $prefix . ': no base dirs';
			return FALSE;
		}

		// search base directories for this namespace prefix

		foreach($this->prefixes[$prefix] as $baseDir) {
	
			/*
			 * replace the namespace prefix with the base directory,
			 * replace namespace separators with directory separators
			 * append ".php"
			 */ 

			$file =
				$baseDir .
				str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) .
				'.php';
	
			// if mapped file exists, require it

			if ($this->requireFile($file)) {
				return $file;
			}
	
			// not in the base directory

			$this->debug[] = $prefix . ': ' . $file . ' not found';
		}
	
		// not found

		return FALSE;
	}
	
	/**
	 * require $file from file system, if it exists
	 * returns success
	 *
	 * @param string $file
	 *
	 * @return boolean
	 */
	protected function requireFile($file) {

		if (file_exists($file)) {
			require $file;
			return TRUE;
		}
		return FALSE;
	}	

}
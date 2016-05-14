<?php

namespace vxPHP\Application;

use vxPHP\Application\Config;
use vxPHP\Observer\EventDispatcher;
use vxPHP\Application\Locale\Locale;
use vxPHP\Application\Exception\ApplicationException;
use vxPHP\Routing\Route;
use vxPHP\Autoload\Psr4;
use vxPHP\Service\ServiceInterface;
use vxPHP\Observer\SubscriberInterface;
use vxPHP\Database\DatabaseInterface;
use vxPHP\Database\DatabaseInterfaceFactory;

/**
 * Application singleton
 *
 * @author Gregor Kofler
 * @version 1.4.1 2016-05-14
 */
class Application {

	/**
	 * @var Application
	 */
	private static $instance;

	/**
	 * @var DatabaseInterface
	 */
	private	$db;

	/**
	 * @var Config
	 */
	private	$config;

	/**
	 * @var EventDispatcher
	 */
	private	$eventDispatcher;

	/**
	 * @var array
	 */
	private $locales = array();

	/**
	 * @var Locale
	 */
	private $currentLocale;

	/**
	 * @var Route
	 */
	private $currentRoute;

	/**
	 * the absolute path to the top level directory of the application (e.g. "/var/www/mydomain/")
	 *
	 * @var string
	 */
	private $rootPath;

	/**
	 * the absolute path to web assets (e.g. "/var/www/mydomain/web/")
	 *
	 * @var string
	 */
	private $absoluteAssetsPath;

	/**
	 * a path prefix which is added to URLs and routes when no URL rewriting is active
	 * needs to be set when the document root points to parent folder of the absolute assets path
	 *
	 * @var string
	 */
	private $relativeAssetsPath;

	/**
	 * path to controllers
	 *
	 * @var string
	 */
	private $controllerPath;

	/**
	 * indicates the use of webserver rewriting for beautified URLs
	 *
	 * @var boolean
	 */
	private $useNiceUris;
	
	/**
	 * indicates whether application runs on a localhost or was called from the command line
	 * 
	 * @var boolean
	 */
	private $isLocal;

	/**
	 * @var multitype:vxPHP\Service\ServiceInterface
	 */
	private $services = array();
	
	/**
	 * @var multitype:vxPHP\Observer\SubscriberInterface
	 */
	private $plugins = array();

	/**
	 * @var Psr4
	 */
	private $loader;

	/**
	 * constructor
	 *
	 * create configuration object, database object
	 * set up dispatcher and plugins (subscribers)
	 * 
	 * @param Config $config
	 */
	private function __construct(Config $config) {

		try {
			$this->config			= $config;
			$this->eventDispatcher	= EventDispatcher::getInstance();

			$this->config->createConst();

			if(!ini_get('date.timezone')) {

				// @todo allow configuration in site.ini.xml

				date_default_timezone_set('Europe/Vienna');
			}

			// initialize available locales

			if(isset($this->config->site->locales)) {
				$this->locales = array_fill_keys($this->config->site->locales, NULL);
			}

			$this->useNiceUris = !!$this->config->site->use_nice_uris;
			
			// set a relative assets path when configured
			
			if(isset($this->config->site->assets_path)) {
				$this->setRelativeAssetsPath($this->config->site->assets_path);
			}
			
			else {
				$this->setRelativeAssetsPath('');
			}

		}

		catch (\Exception $e) {
			printf(
				'<div style="border: solid 2px; color: #c00; font-weight: bold; padding: 1em; width: 40em; margin: auto; ">Application Error!<br>Message: %s</div>',
				$e->getMessage()
			);
			exit();
		}

	}

	private function __clone() {}

	/**
	 * get Application instance
	 *
	 * @param Config $config
	 * @return Application
	 */
	public static function getInstance(Config $config = NULL) {

		if(is_null(self::$instance)) {
			if(is_null($config)) {
				throw new ApplicationException('No configuration object provided. Cannot instantiate application.');
			}
			self::$instance = new Application($config);
		}

		return self::$instance;

	}

	/**
	 * unregister all previously registered plugins
	 * read plugin configuration from Config
	 * and register all configured plugins
	 * 
	 * @return Application
	 */
	public function registerPlugins() {
		
		if($this->plugins) {
			foreach($this->plugins as $plugin) {
				$this->eventDispatcher->removeSubscriber($plugin);
			}
		}

		$this->plugins = array();
		
		// initialize plugins (if configured)
		
		if($this->config->plugins) {

			foreach(array_keys($this->config->plugins) as $pluginId) {
				$this->plugins[] = $this->initializePlugin($pluginId);
			}

		}

		return $this; 

	}

	/**
	 * make Psr4 loader in application available
	 * 
	 * @param Psr4 $loader
	 * @return Application
	 */
	public function setLoader(Psr4 $loader) {

		$this->loader = $loader;
		return $this;
		
	}
	
	/**
	 * returns default database object reference
	 *
	 * @return DatabaseInterface
	 */
	public function getDb() {

		if(empty($this->db)) {

			if(empty($this->config->db)) {
				return NULL;
			}

			$config = $this->config->db;

			$this->db = DatabaseInterfaceFactory::create(
				isset($config->type) ? $config->type : 'mysql',
				[
					'host'		=> $config->host,
					'dbname'	=> $config->name,
					'user'		=> $config->user,
					'pass'		=> $config->pass,
					'logtype'	=> $config->logtype
				]
			);
		}

		return $this->db;

	}

	/**
	 * returns config instance reference
	 *
	 * @return Config
	 */
	public function getConfig() {

		return $this->config;

	}

	/**
	 * return a service instance
	 * service instances are lazily initialized upon first request
	 * services are expected in the src/services folder of the application and can be namespaced
	 * extra arguments are passed on to the constructor method of the service 
	 * 
	 * @param string $serviceId
	 * @return \vxPHP\Application\multitype:ServiceInterface
	 */
	public function getService($serviceId) {

		$args = func_get_args();
		$service = $this->initializeService($serviceId, array_splice($args, 1));
		$this->services[] = $service;

		return $service;

	}

	/**
	 * returns event dispatcher instance reference
	 *
	 * @return EventDispatcher
	 */
	public function getEventDispatcher() {

		return $this->eventDispatcher;

	}

	/**
	 * get the currently active route
	 *
	 * @return Route
	 */
	public function getCurrentRoute() {

		return $this->currentRoute;

	}

	/**
	 * retrieve setting for nice uris
	 *
	 * @return boolean
	 */
	public function hasNiceUris() {

		return $this->useNiceUris;

	}

	/**
	 * returns true when the application
	 * was called from the command line or in a localhost environment
	 * 
	 * @return boolean
	 */
	public function runsLocally() {

		if(is_null($this->isLocal)) {
			
			$remote =
				isset($_SERVER['HTTP_CLIENT_IP']) ||
				isset($_SERVER['HTTP_X_FORWARDED_FOR']) ||
				!(in_array(@$_SERVER['REMOTE_ADDR'], array('127.0.0.1', 'fe80::1', '::1')) || PHP_SAPI === 'cli-server');

			$this->isLocal = PHP_SAPI === 'cli' || !$remote;

		}		

		return $this->isLocal;

	}

	/**
	 * get absolute path to controller classes
	 *
	 * @return string
	 */
	public function getControllerPath() {

		// lazy init

		if(is_null($this->controllerPath)) {

			$this->controllerPath =
				$this->rootPath .
				'src' . DIRECTORY_SEPARATOR .
				'controller' . DIRECTORY_SEPARATOR;

		}

		return $this->controllerPath;
	}

	/**
	 * set absolute assets path
	 * the relative assets path is updated
	 *
	 * @param string $path
	 * @return Application
	 * @throws ApplicationException
	 */
	public function setAbsoluteAssetsPath($path) {

		$path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		if(!is_null($this->rootPath) && 0 !== strpos($path, $this->rootPath)) {
			throw new ApplicationException("'$path' not within application path '{$this->rootPath}'", ApplicationException::PATH_MISMATCH);
		}

		$this->absoluteAssetsPath = $path;

		return $this;

	}

	/**
	 * get absolute path to web assets
	 * directory separator is platform dependent
	 *
	 * @return string
	 */
	public function getAbsoluteAssetsPath() {

		return $this->absoluteAssetsPath;
	
	}

	/**
	 * set relative path to web assets
	 * 
	 * @param string $path
	 * @return Application
	 */
	public function setRelativeAssetsPath($path) {

		$this->relativeAssetsPath = trim(str_replace(DIRECTORY_SEPARATOR, '/', $path), '/');
		return $this;
	
	}
	
	/**
	 * get relative path to web assets
	 * directory separator is always '/'
	 *
	 * @return string
	 */
	public function getRelativeAssetsPath() {
	
		return $this->relativeAssetsPath;
	
	}

	/**
	 * set root path of application
	 * if an assetspath is set, the relative assets path is updated
	 *
	 * @param string $path
	 * @return Application
	 * @throws ApplicationException
	 */
	public function setRootPath($path) {

		$path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		if(!is_null($this->absoluteAssetsPath) && 0 !== strpos($this->absoluteAssetsPath, $path)) {
			throw new ApplicationException("'$path' not a parent of assets path '{$this->absoluteAssetsPath}'", ApplicationException::PATH_MISMATCH);
		}

		$this->rootPath = $path;
		$this->relativeAssetsPath = str_replace(DIRECTORY_SEPARATOR, '/', str_replace($this->rootPath, '', (string) $this->absoluteAssetsPath));

		return $this;

	}

	/**
	 * get absolute path to application root
	 * directory separator is platform dependent
	 *
	 * @return string
	 */
	public function getRootPath() {

		return $this->rootPath;

	}

	/**
	 * tries to interpret $path as relative path within assets path
	 * if $path is an absolute path (starting with "/" or "c:\") it is returned unchanged
	 * otherwise $path is extended with Application::absoluteAssetsPath to the left
	 *
	 * @param string $path
	 */
	public function extendToAbsoluteAssetsPath($path) {

		if(strpos($path, DIRECTORY_SEPARATOR) === 0 || strpos($path, ':\\') === 1) {
			return $path;
		}

		else {

			$pathSegments	= explode(DIRECTORY_SEPARATOR, $path);

			// eliminate doubling of assets path
			// a subdir with the name of the assets path as a child of the assets path will *not* work

			if($pathSegments[0] === trim($this->getRelativeAssetsPath(), '/')) {
				array_shift($pathSegments);
			}

			return $this->getAbsoluteAssetsPath() . implode(DIRECTORY_SEPARATOR, $pathSegments);
		}

	}

	/**
	 * returns an array with available Locale instances
	 * because of lazy instantiation, missing instances are created now
	 *
	 * @return array
	 */
	public function getAvailableLocales() {

		foreach($this->locales as $id => $l) {
			if(!$l) {
				$this->locales[$id] = new Locale($id);
			}
		}

		return $this->locales;
	}

	/**
	 * checks whether locale can be instantiated
	 * at this point instance might not have been created
	 *
	 * @param string $localeId
	 * @return boolean
	 */
	public function hasLocale($localeId) {

		return array_key_exists(strtolower($localeId), $this->locales);

	}

	/**
	 *
	 * @param string $localeId
	 */
	public function getLocale($localeId) {

		$localeId = strtolower($localeId);

		if(!array_key_exists($localeId, $this->locales)) {
			throw new ApplicationException("Locale '$localeId' does not exist.", ApplicationException::INVALID_LOCALE);
		}

		if(is_null($this->locales[$localeId])) {
			$this->locales[$localeId] = new Locale($localeId);
		}

		return $this->locales[$localeId];
	}

	/**
	 * get the currently selected locale
	 *
	 * @return Locale
	 */
	public function getCurrentLocale() {

		return $this->currentLocale;

	}

	/**
	 * set the current locale
	 *
	 * @param Locale $locale
	 * @return Application
	 */
	public function setCurrentLocale(Locale $locale) {

		$this->currentLocale = $locale;
		return $this;

	}

	/**
	 * set the current route, avoids re-parsing of path
	 *
	 * @param Route $route
	 * @return Application
	 */
	public function setCurrentRoute(Route $route) {

		$this->currentRoute = $route;
		return $this;
		
	}
	
	/**
	 * create and initialize a service instance
	 * 
	 * @param string $serviceId
	 * @param array $constructorArguments
	 * @throws ApplicationException
	 * @return ServiceInterface
	 */
	private function initializeService($serviceId, array $constructorArguments) {
		
		if(!isset($this->config->services[$serviceId])) {
			throw new ApplicationException(sprintf("Service '%s' not configured.", $serviceId));
		}

		$configData = $this->config->services[$serviceId];

		// get class name

		$class	= $configData['class'];

		// check whether class was loaded previously

		if(!isset($configData['loaded'])) {

			$file	= $this->rootPath . 'src/' . $configData['classPath'] . $class . '.php';
			
			if(!file_exists($file)) {
				throw new ApplicationException(sprintf("Class file '%s' for service '%s' not found.", $file, $serviceId));
			}
	
			require $file;
		
			$this->config->services[$serviceId]['loaded'] = TRUE;

		}

		// use reflection to pass on additional constructor arguments

		$reflector	= new \ReflectionClass($class);
		$service	= $reflector->newInstanceArgs($constructorArguments);

		// check whether instance implements ServiceInterface  

		if(!$service instanceof ServiceInterface) {
			throw new ApplicationException(sprintf("Service '%s' (class %s) does not implement the ServiceInterface.", $serviceId, $class));
		}

		// set parameters
		
		$service->setParameters($configData['parameters']);
		
		return $service;

	}
	
	/**
	 * create, initialize and register a plugin instance
	 * 
	 * @param string $pluginId
	 * @throws ApplicationException
	 * @return ListenerInterface
	 */
	private function initializePlugin($pluginId) {

		$configData = $this->config->plugins[$pluginId];

		// load class file

		$class	= $configData['class'];
		$file	= $this->rootPath . 'src/' . $configData['classPath'] . $class . '.php';

		if(!file_exists($file)) {
			throw new ApplicationException(sprintf("Class file '%s' for plugin '%s' not found.", $file, $pluginId));
		}

		require $file;
		
		// create instance

		$plugin = new $class;
		
		// check whether instance implements SubscriberInterface
		
		if(!$plugin instanceof SubscriberInterface) {
			throw new ApplicationException(sprintf("Plugin '%s' (class %s) does not implement the SubscriberInterface.", $pluginId, $class));
		}
		
		// set parameters
		
		if(!empty($configData['parameters'])) {

			if(!method_exists($plugin, 'setParameters')) {
				throw new ApplicationException(sprintf("Plugin '%s' (class %s) does not provide a 'setParameters' method but has parameters configured.", $pluginId, $class));
			}
			
			$plugin->setParameters($configData['parameters']);
		}

		// register plugin with dispatcher

		EventDispatcher::getInstance()->addSubscriber($plugin);
		
		return $plugin;

	}
}

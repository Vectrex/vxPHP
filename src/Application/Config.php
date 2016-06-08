<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Application;

use vxPHP\Application\Exception\ConfigException;

use vxPHP\Webpage\Menu\Menu;
use vxPHP\Webpage\MenuEntry\MenuEntry;
use vxPHP\Webpage\MenuEntry\DynamicMenuEntry;

use vxPHP\Routing\Route;

/**
 * Config
 * creates configuration singleton by parsing the XML ini-file
 *
 * @version 1.7.0 2016-06-08
 *
 * @todo refresh() method
 */
class Config {

	/**
	 * @var \stdClass
	 */
	public	$site;

	/**
	 * @var \stdClass
	 */
	public	$db;

	/**
	 * @var \stdClass
	 */
	public	$mail;

	/**
	 * @var \stdClass
	 */
	public	$binaries;

	/**
	 * @var array
	 */
	public	$paths;

	/**
	 * @var Route[]
	 */
	public	$routes;

	/**
	 * @var Menu[]
	 */
	public	$menus;

	/**
	 * @var array
	 */
	public	$server;

	/**
	 * @var array
	 * 
	 * holds configuration of services
	 */
	public	$services;

	/**
	 * @var array
	 * 
	 * holds all configured plugins (event subscribers)
	 */
	public	$plugins;
	
	/**
	 * @var \stdClass
	 * 
	 * holds configuration for templating
	 */
	public	$templating;	

	/**
	 * @var boolean
	 */
	private	$isLocalhost;
			
	/**
	 * @var array
	 * 
	 * holds sections of config file which are parsed
	 */
	private	$sections	= [];

	/**
	 * create config instance
	 * if section is specified, only certain sections of the config file are parsed
	 *
	 * @param string $xmlFile
	 * @param array $sections
	 * @throws ConfigException
	 */
	public function __construct($xmlFile, array $sections = []) {

		$this->sections	= $sections;

		if(!($config = simplexml_load_file($xmlFile))) {
			throw new ConfigException("Missing or malformed '" . $xmlFile ."'.");
		}

		$this->parseConfig($config);
		$this->getServerConfig();
	}

	/**
	 * iterates through the sections of the config file
	 * and calls init function
	 *
	 * @param \SimpleXMLElement $config
	 * @throws ConfigException
	 * @return void
	 */
	private function parseConfig(\SimpleXMLElement $config) {

		try {

			// determine server context, missing SERVER_ADDR assumes localhost/CLI

			$this->isLocalhost = !isset($_SERVER['SERVER_ADDR']) || !!preg_match('/^(?:127|192|1|0)(?:\.\d{1,3}){3}$/', $_SERVER['SERVER_ADDR']);

			// allow parsing of specific sections

			foreach($config->children() as $section) {

				$sectionName = $section->getName();

				if(empty($this->sections) || in_array($sectionName, $this->sections)) {

					$methodName =	'parse'.
									ucfirst(
										preg_replace_callback('/_([a-z])/', function($match) { return strtoupper($match[1]); }, $sectionName)
									).'Settings';

					if(method_exists($this, $methodName)) {
						call_user_func([$this, $methodName], $section);
					}
				}
			}
		}

		catch(ConfigException $e) {
			throw $e;
		}
	}

	/**
	 * parse db settings
	 *
	 * @param SimpleXMLElement $db
	 */
	private function parseDbSettings(\SimpleXMLElement $db) {

		$context = $this->isLocalhost ? 'local' : 'remote';

		$d = $db->xpath("db_connection[@context='$context']");

		if(empty($d)) {
			$d = $db->xpath('db_connection');
		}

		if(!empty($d)) {
			$this->db = new \stdClass();

			foreach($d[0]->children() as $k => $v) {
				$this->db->$k = (string) $v;
			}
		}
	}

	/**
	 * parses all (optional) mail settings
	 *
	 * @param SimpleXMLElement $mail
	 */
	private function parseMailSettings(\SimpleXMLElement $mail) {

		if(!empty($mail->mailer[0])) {

			$mailer = $mail->mailer[0];

			$this->mail = new \stdClass();
			$this->mail->mailer = new \stdClass();

			$attr = $mailer->attributes();

			$this->mail->mailer->class = (string) $attr['class'];

			foreach($mailer->children() as $k => $v) {
				$this->mail->mailer->$k = (string) $mailer->$k;
			}
		}
	}

	/**
	 * parse settings for binaries
	 *
	 * @param SimpleXmlElement $binaries
	 * @throws ConfigException
	 */
	private function parseBinariesSettings(\SimpleXmlElement $binaries) {

		$context = $this->isLocalhost ? 'local' : 'remote';

		$e = $binaries->xpath("executables[@context='$context']");

		if(empty($e)) {
			$e = $binaries->xpath('executables');
		}

		if(!empty($e)) {
			$p = $e[0]->path;
			if(empty($p)) {
				throw new ConfigException('Malformed "site.ini.xml"! Missing path for binaries.');
			}

			$this->binaries = new \stdClass;
			$this->binaries->path = rtrim((string) $p[0], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

			foreach($e[0]->executable as $v) {
				$id = (string) $v->attributes()->id;
				foreach($v->attributes() as $k => $v) {
					$this->binaries->executables[$id][$k] = (string) $v;
				}
			}
		}
	}

	/**
	 * parse general website settings
	 *
	 * @param SimpleXMLElement $site
	 */
	private function parseSiteSettings(\SimpleXMLElement $site) {

		$this->site = new \stdClass;

		$this->site->use_nice_uris = FALSE;

		foreach($site[0] as $k => $v) {

			$v = trim((string) $v);

			switch ($k) {

				case 'locales':
					break;

				case 'site->use_nice_uris':
					if($v === '1') {
						$this->site->use_nice_uris = TRUE;
					}
					break;

				default:
					$this->site->$k = $v;
			}
		}

		if(isset($site->locales)) {
			$this->site->locales = [];
			$l = $site->locales;
			foreach($l[0] as $locale) {
				array_push($this->site->locales, (string) $locale->attributes()->value);
				if(!empty($locale->attributes()->default)) {
					$this->site->default_locale = (string) $locale->attributes()->value;
				}
			}
		}
	}
	
	/**
	 * parse templating configuration
	 * currently only filters for SimpleTemplate templates and their configuration are parsed
	 * 
	 * @param \SimpleXMLElement $templating
	 */
	private function parseTemplatingSettings(\SimpleXMLElement $templating) {

		$this->templating = new \stdClass;
		$this->templating->filters = [];

		if(isset($templating->filters->filter)) {

			foreach($templating->filters->filter as $filter) {

				$a			= $filter->attributes();
				$id			= (string) $a->id;
				$class		= (string) $a->class;
				
				if(!$id) {
					throw new ConfigException('Templating filter without id found.');
				}
				
				if(!$class)	{
					throw new ConfigException(sprintf("No class for templating filter '%s' configured.", $id));
				}
				
				if(isset($this->templating->filters[$id])) {
					throw new ConfigException(sprintf("Templating filter '%s' has already been defined.", $id));
				}

				// clean path delimiters

				$class		= ltrim(str_replace('\\', '/', $class), '/');
				
				// seperate class name and path to class along last slash
				
				$delimPos	= strrpos($class, '/');
				$classPath	= '';
				
				if($delimPos !== FALSE) {
				
					$classPath	= substr($class, 0, $delimPos + 1);
					$class		= substr($class, $delimPos + 1);
				
				}

				$this->templating->filters[$id] = [
					'class'			=> $class,
					'classPath'		=> $classPath,
					'parameters'	=> (string) $a->parameters
				];
			}
		}

	}
	

	/**
	 * parse various path setting
	 *
	 * @param SimpleXMLElement $paths
	 */
	private function parsePathsSettings(\SimpleXMLElement $paths) {

		foreach($paths->children() as $p) {

			$a = $p->attributes();
			$p = trim((string) $a->subdir, '/').'/';

			if(substr($p, 0, 1) == '/') {
				$this->paths[(string) $a->id]['subdir']		= $p;
				$this->paths[(string) $a->id]['absolute']	= TRUE;
			}
			else {
				$this->paths[(string) $a->id]['subdir']		= "/$p";
				$this->paths[(string) $a->id]['absolute']	= FALSE;

			}
			$this->paths[(string) $a->id]['access'] = empty($a->access) ? 'r' : (string) $a->access;
		}
	}

	/**
	 * parse page routes
	 * called seperately for differing script attributes
	 *
	 * @param SimpleXMLElement $pages
	 */
	private function parsePagesSettings(\SimpleXMLElement $pages) {

		$scriptName = empty($pages->attributes()->script) ? $this->site->root_document : (string) $pages->attributes()->script;
		$redirect	= empty($pages->attributes()->default_redirect) ? NULL : (string) $pages->attributes()->default_redirect;

		foreach($pages->page as $page) {

			$parameters = [
				'redirect' => $redirect
			];

			$a = $page->attributes();

			// get route id

			$pageId	= (string) $a->id;
			
			if($pageId === '') {
				throw new ConfigException('Route with missing or invalid id found.');
			}

			// read optional controller

			if(isset($a->controller)) {
				$parameters['controller'] = (string) $a->controller;
			}

			// read optional controller method

			if(isset($a->method)) {
				$parameters['method'] = (string) $a->method;
			}

			// read optional controller method
			
			if(isset($a->request_methods)) {
				$allowedMethods	= 'GET POST PUT DELETE';
				$requestMethods	= preg_split('~\s*,\s*~', strtoupper((string) $a->request_methods));
				
				foreach($requestMethods as $requestMethod) {
					if(strpos($allowedMethods, $requestMethod) === -1) {
						throw new ConfigException(sprintf("Invalid request method '%s' for route '%s'.", $requestMethod, $pageId));
					}
				}
				$parameters['requestMethods'] = $requestMethods;
			}

			// when no path is defined page id will be used for route lookup

			if(isset($a->path)) {

				// initialize lookup expression

				$rex = (string) $a->path;

				// extract route parameters and default values

				if(preg_match_all('~\{(.*?)(=.*?)?\}~', (string) $a->path, $matches)) {

					$placeholders = [];

					if(!empty($matches[1])) {

						foreach($matches[1] as $ndx => $name) {

							$name = strtolower($name);
							
							if(!empty($matches[2][$ndx])) {

								$placeholders[$name] = [
									'name' => $name,
									'default' => substr($matches[2][$ndx], 1)
								];

								// turn this path parameter into regexp and make it optional

								$rex = preg_replace('~\/{.*?\}~', '(?:/([^/]+))?', $rex, 1);

							}

							else {

								$placeholders[$name] = [
									'name' => $name
								];

								// turn this path parameter into regexp

								$rex = preg_replace('~\{.*?\}~', '([^/]+)', $rex, 1);

							}
						}
					}

					$parameters['placeholders'] = $placeholders;
				}

				$parameters['path'] = (string) $a->path;

			}

			else {
				$rex = $pageId;
			}

			$parameters['match'] = $rex;

			if(isset($a->auth)) {

				$auth = strtoupper(trim((string) $a->auth));

				if(defined("vxPHP\\User\\User::AUTH_$auth")) {
					$auth = constant("vxPHP\\User\\User::AUTH_$auth");

					if(isset($a->auth_parameters)) {
						$parameters['authParameters'] = trim((string) $a->auth_parameters);
					}
				}
				else {
					$auth = -1;
				}

				$parameters['auth'] = $auth;
			}

			if(isset($this->routes[$scriptName][$pageId])) {
				throw new ConfigException(sprintf("Route '%s' for script '%s' found more than once.", $pageId, $scriptName));
			}

			$route = new Route($pageId, $scriptName, $parameters);
			$this->routes[$scriptName][$route->getRouteId()] = $route;

		}
	}

	/**
	 * parse menu tree
	 *
	 * @param SimpleXMLElement $menus
	 */
	private function parseMenusSettings(\SimpleXMLElement $menus) {

		foreach ($menus->menu as $menu) {

			$menuInstance = $this->parseMenu($menu);
			$this->menus[$menuInstance->getId()] = $menuInstance;

		}

	}

	/**
	 * parse settings for services
	 * only service id, class and parameters are parsed
	 * lazy initialization is handled by Application instance
	 * 
	 * @param \SimpleXMLElement $services
	 * @throws ConfigException
	 */
	private function parseServicesSettings(\SimpleXMLElement $services) {

		foreach($services->service as $service) {

			if(!($id = (string) $service->attributes()->id)) {
				throw new ConfigException('Service without id found.');
			}

			if(isset($this->services[$id])) {
				throw new ConfigException(sprintf("Service '%s' has already been defined.", $id));
			}

			if(!($class = (string) $service->attributes()->class)) {
				throw new ConfigException(sprintf("No class for service '%s' configured.", $id));
			}

			// clean path delimiters

			$class		= ltrim(str_replace('\\', '/', $class), '/');

			// seperate class name and path to class along last slash

			$delimPos	= strrpos($class, '/');
			$classPath	= '';

			if($delimPos !== FALSE) {

				$classPath	= substr($class, 0, $delimPos + 1);
				$class		= substr($class, $delimPos + 1);

			}

			$this->services[$id] = [
				'class'			=> $class,
				'classPath'		=> $classPath,
				'parameters'	=> []
			];

			foreach($service->parameter as $parameter) {

				$name	= (string) $parameter->attributes()->name;
				$value	= (string) $parameter->attributes()->value;

				if(!$name) {
					throw new ConfigException(sprintf("A parameter for service '%s' has no name.", $id));
				}

				$this->services[$id]['parameters'][$name] = $value;

			}

		}

	}

	/**
	 * parses settings for plugins
	 * plugin id, class, events and parameters are parsed
	 * initialization (not lazy) is handled by Application instance
	 *
	 * @param SimpleXMLElement $plugins
	 */
	private function parsePluginsSettings(\SimpleXMLElement $plugins) {

		foreach($plugins->plugin as $plugin) {

			if(!($id = (string) $plugin->attributes()->id)) {
				throw new ConfigException('Plugin without id found.');
			}
			
			if(isset($this->plugins[$id])) {
				throw new ConfigException(sprintf("Plugin '%s' has already been defined.", $id));
			}
			
			if(!($class = (string) $plugin->attributes()->class)) {
				throw new ConfigException(sprintf("No class for plugin '%s' configured.", $id));
			}
			
			// clean path delimiters

			$class		= ltrim(str_replace('\\', '/', $class), '/');
			
			// seperate class name and path to class along last slash
			
			$delimPos	= strrpos($class, '/');
			$classPath	= '';

			if($delimPos !== FALSE) {
			
				$classPath	= substr($class, 0, $delimPos + 1);
				$class		= substr($class, $delimPos + 1);
			
			}

			$this->plugins[$id] = [
				'class'			=> $class,
				'classPath'		=> $classPath,
				'parameters'	=> []
			];

			foreach($plugin->parameter as $parameter) {
			
				$name	= (string) $parameter->attributes()->name;
				$value	= (string) $parameter->attributes()->value;
			
				if(!$name) {
					throw new ConfigException(sprintf("A parameter for plugin '%s' has no name.", $id));
				}
			
				$this->plugins[$id]['parameters'][$name] = $value;
			
			}

		}

	}
	
	/**
	 * Parse XML menu entries and creates menu instance
	 *
	 * @param simpleXmlElement $menu
	 * @return Menu
	 */
	private function parseMenu(\SimpleXMLElement $menu) {

		$a			= $menu->attributes();
		$root		= isset($a->script)	? (string) $a->script : $this->site->root_document;

		$m = new Menu(
			$root,
			!empty($a->id)										? (string) $a->id		: NULL,
			isset($a->type) && (string)	$a->type == 'dynamic'	? 'dynamic'				: 'static',
			isset($a->method)									? (string) $a->method	: NULL
		);

		if(isset($a->auth)) {

			// set optional authentication level; if level is not defined, menu is locked for everyone
			// if auth level is defined, additional authentication parameters can be set

			$menuAuth = strtoupper(trim((string) $a->auth));

			if(defined("vxPHP\\User\\User::AUTH_$menuAuth")) {
				$m->setAuth(constant("vxPHP\\User\\User::AUTH_$menuAuth"));

				if(isset($a->auth_parameters)) {
					$m->setAuthParameters((string) $a->auth_parameters);
				}
			}
			else {
				$m->setAuth(-1);
			}
		}
		
		else {
			$menuAuth = NULL;
		}

		foreach($menu->children() as $entry) {

			if($entry->getName() == 'menuentry') {

				$a = $entry->attributes();

				if(isset($a->page) && isset($a->path)) {
				
					throw new ConfigException(sprintf("Menu entry with both page ('%s') and path ('%s') attribute found.", (string) $a->page, (string) $a->path));
				
				}

				// menu entry comes with a path attribute (which can also link an external resource)
				
				if(isset($a->path)) {
					
					$path = (string) $a->path;
					$local = strpos($path, '/') !== 0 && !preg_match('~^[a-z]+://~', $path);
					
					$e = new MenuEntry($path, $a, $local);
						
				}

				// menu entry comes with a page attribute, in this case the route path is used

				else if(isset($a->page)) {

					$page = (string) $a->page;

					if(!isset($this->routes[$m->getScript()][$page])) {

						throw new ConfigException(sprintf(
							"No route for menu entry ('%s') found. Available routes for script '%s' are '%s'.",
							$page,
							$m->getScript(),
							empty($this->routes[$m->getScript()]) ? 'none' : implode("', '", array_keys($this->routes[$m->getScript()]))
						));

					}

					$e = new MenuEntry((string) $this->routes[$m->getScript()][$page]->getPath(NULL, TRUE), $a, TRUE);

				}
				
				// handle authentication settings of menu entry
				
				if($menuAuth || isset($a->auth)) {
				
					// fallback to menu settings, when auth attribute is not set
				
					if(!isset($a->auth)) {
							
						$e->setAuth($m->getAuth());
						$e->setAuthParameters($m->getAuthParameters());
				
					}
				
					else {
				
						// set optional authentication level; if level is not defined, entry is locked for everyone
						// if auth level is defined, additional authentication parameters can be set
				
						$auth = strtoupper(trim((string) $a->auth));
				
						if(defined("UserAbstract::AUTH_$auth")) {
							$e->setAuth(constant("UserAbstract::AUTH_$auth"));
				
							if(isset($a->auth_parameters)) {
								$e->setAuthParameters((string) $a->auth_parameters);
							}
						}
						else {
							$e->setAuth(-1);
						}
					}
				}
				
				$m->appendEntry($e);
				
				if(isset($entry->menu)) {
					$e->appendMenu($this->parseMenu($entry->menu));
				}

			}

			else if($entry->getName() == 'menuentry_placeholder') {

				$a = $entry->attributes();
				$e = new DynamicMenuEntry(NULL, $a);
				$m->appendEntry($e);

			}
		}

		return $m;

	}

	/**
	 * @todo refresh config by re-parsing XML file
	 */
	public function refresh() {
	}

	/**
	 * create constants for simple access to certain configuration settings
	 */
	public function createConst() {
		$properties = get_object_vars($this);

		if(isset($properties['db'])) {
			foreach($properties['db'] as $k => $v) {
				if(is_scalar($v)) {
					$k = strtoupper($k);
					if(!defined("DB$k")) { define("DB$k", $v); }
				}
			}
		}

		if(isset($properties['site'])) {
			foreach($properties['site'] as $k => $v) {
				if(is_scalar($v)) {
					$k = strtoupper($k);
					if(!defined($k)) { define($k, $v); }
				}
			}
		}

		if(isset($properties['paths'])) {
			foreach($properties['paths'] as $k => $v) {
				$k = strtoupper($k);
				if(!defined($k)) { define($k, $v['subdir']); }
			}
		}

		$locale = localeconv();

		foreach($locale as $k => $v) {
			$k = strtoupper($k);
			if(!defined($k) && !is_array($v)) { define($k, $v); }
		}
	}

	/**
	 * returns all paths matching access criteria
	 *
	 * @param string $access
	 * @return paths
	 */
	public function getPaths($access = 'rw') {
		$paths = [];
		foreach($this->paths as $p) {
			if($p['access'] === $access) {
				array_push($paths, $p);
			}
		}
		return $paths;
	}

	/**
	 * add particular information regarding server configuration, like PHP extensions
	 */
	private function getServerConfig() {

		$this->server['apc_on'] = extension_loaded('apc') && function_exists('apc_add') && ini_get('apc.enabled') && ini_get('apc.rfc1867');

		$fs = ini_get('upload_max_filesize');
		$suffix = strtoupper(substr($fs, -1));
		switch($suffix) {
			case 'K':
				$mult = 1024; break;
			case 'M':
				$mult = 1024*1024; break;
			case 'G':
				$mult = 1024*1024*1024; break;
			default:
				$mult = 0;
		}

		$this->server['max_upload_filesize'] = $mult ? (float) (substr($fs, 0, -1)) * $mult : (int) $fs;

	}
}

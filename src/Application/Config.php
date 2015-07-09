<?php

namespace vxPHP\Application;

use vxPHP\Application\Exception\ConfigException;

use vxPHP\Webpage\Menu\Menu;
use vxPHP\Webpage\MenuEntry\MenuEntry;
use vxPHP\Webpage\MenuEntry\DynamicMenuEntry;

use vxPHP\Observer\EventDispatcher;
use vxPHP\Routing\Route;

/**
 * Config
 * creates configuration singleton by parsing the XML ini-file
 *
 * @version 1.3.0 2015-07-09
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
			 * @var array
			 */
	public	$routes;

			/**
			 * @var array
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
			 * holds all configured plugins
			 */
	public	$plugins;

			/**
			 * @var boolean
			 */
	private	$isLocalhost;
			
			/**
			 * @var array
			 * 
			 * holds sections of config file which are parsed
			 */
	private	$sections	= array();
			
	/**
	 * create config instance
	 * if section is specified, only certain sections of the config file are parsed
	 *
	 * @param string $xmlFile
	 * @param array $sections
	 * @throws ConfigException
	 */
	public function __construct($xmlFile, array $sections = array()) {

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
						call_user_func(array($this, $methodName), $section);
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
			$this->site->locales = array();
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

			$parameters = array('redirect' => $redirect);

			$a = $page->attributes();

			// get route id

			$pageId	= (string) $a->id;
			
			if($pageId === '') {
				throw new ConfigException('Invalid or missing route id.');
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
						throw new ConfigException('Invalid request method ' . $requestMethod . '.');
					}
				}
				$parameters['requestMethods'] = $requestMethods;
			}

			// when no path is defined page id will be used for route lookup

			if(isset($a->path)) {

				// initialize lookup expression

				$rex = (string) $a->path;

				// extract route parameters and default values

				if(preg_match_all('~\{(.*?)(?:=(.*?))?\}~', (string) $a->path, $matches)) {

					$placeholders = array();

					if(!empty($matches[1])) {

						foreach($matches[1] as $ndx => $name) {

							if(!empty($matches[2][$ndx])) {

								$placeholders[] = array('name' => strtolower($name), 'default' => $matches[2][$ndx]);

								// turn this path parameter into regexp and make it optional

								$rex = preg_replace('~\/{.*?\}~', '(?:/([^/]+))?', $rex, 1);

							}

							else {

								$placeholders[] = array('name' => strtolower($name));

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

			$this->routes[$scriptName][] = new Route($pageId, $scriptName, $parameters);
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
				throw new ConfigException(sprintf("Service '%s' already defined.", $id));
			}

			if(!($class = (string) $service->attributes()->class)) {
				throw new ConfigException(sprintf("No class for service '%s' configured.", $id));
			}

			$this->services[$id] = array(
				'class'			=> $class,
				'parameters'	=> array()
			);

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
				throw new ConfigException(sprintf("Plugin '%s' already defined.", $id));
			}
			
			if(!($class = (string) $plugin->attributes()->class)) {
				throw new ConfigException(sprintf("No class for plugin '%s' configured.", $id));
			}
			
			if(!($listenTo = (string) $plugin->attributes()->listen_to)) {
				throw new ConfigException(sprintf("No events to listen to for plugin '%s' configured.", $id));
			}
			
			$this->plugins[$id] = array(
				'class'			=> $class,
				'listenTo'		=> preg_split('~\s*,\s*~', $listenTo),
				'parameters'	=> array()
			);

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

				if(isset($a->page)) {

					$page = (string) $a->page;
					$local = strpos($page, '/') !== 0 && !preg_match('~^[a-z]+://~', $page);

					$e = new MenuEntry((string) $a->page, $a, $local);

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
		$paths = array();
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

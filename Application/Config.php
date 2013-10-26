<?php

namespace vxPHP\Application;

use vxPHP\Application\Exception\ConfigException;

use vxPHP\Webpage\Menu\Menu;
use vxPHP\Webpage\MenuEntry\MenuEntry;
use vxPHP\Webpage\MenuEntry\DynamicMenuEntry;

use vxPHP\Observer\EventDispatcher;
use vxPHP\Http\Route;

/**
 * Config
 * creates configuration singleton by parsing XML ini-file
 *
 * @version 0.9.2 2013-10-26
 *
 * @todo refresh() method
 */
class Config {

	public	$site,
			$db,
			$mail,
			$paths,
			$binaries,
			$routes,
			$menus,
			$server,
			$controllerPath;

	public $locales =  array(
		'de' => array('de', 'de_DE', 'deu_deu'),
		'en' => array('en', 'en_GB', 'en_US', 'eng')
	);

			/**
			 * @var Config
			 */
	private static $instance;

	private	$isLocalhost,
			$xmlFile,
			$xmlFileTS,
			$sections = array(),
			$config,
			$plugins = array();

	/**
	 * create config instance
	 * if section is specified, only certain sections of the config file are parsed
	 *
	 * @param string $xmlFile
	 * @param array $sections
	 * @throws ConfigException
	 */
	private function __construct($xmlFile, array $sections) {

		$this->xmlFile	= $xmlFile;
		$this->sections	= $sections;

		if(!$this->config = simplexml_load_file($this->xmlFile)) {
			throw new ConfigException("Missing or malformed '$xmlFile'!");
		}

		$this->xmlFileTS = filemtime($this->xmlFile);

		$this->parseConfig();
		$this->getServerConfig();
		$this->setControllerPath();

		unset($this->config);
	}

	private function __clone() {}

	public static function getInstance($xmlFile, array $sections = array()) {
		if(
			isset($_SESSION['CONFIG']->xmlFileTS) &&
			$_SESSION['CONFIG']->xmlFileTS == filemtime($xmlFile)
		) {
			self::$instance = $_SESSION['CONFIG'];
			return self::$instance;
		}
		if(is_null(self::$instance)) {
			self::$instance = new Config($xmlFile, $sections);
		}
		$_SESSION['CONFIG'] = self::$instance;
		return self::$instance;
	}

	/**
	 * set path to controller classes
	 * Route reads this path
	 *
	 * @todo remove hard coded path configuration
	 * @todo handle non-web environment
	 */
	private function setControllerPath() {

		$this->controllerPath =
			rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR .
			'src' . DIRECTORY_SEPARATOR .
			'controller' . DIRECTORY_SEPARATOR;
	}

	/**
	 * iterates through the sections of the config file
	 * and calls init function
	 *
	 * @throws ConfigException
	 * @return void
	 */
	private function parseConfig() {

		try {

			// determine server context

			$this->isLocalhost = !!preg_match('/^(?:127|192|1|0)(?:\.\d{1,3}){3}$/', $_SERVER['SERVER_ADDR']);

			// allow parsing of specific sections

			foreach($this->config->children() as $section) {
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

			$this->binaries = new \StdClass;
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

		$this->site = new \StdClass;

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
			if(isset($this->site->default_locale)) {
				$this->site->current_locale = $this->site->default_locale;
			}
			else {
				$this->site->current_locale = $this->site->locales[0];
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
	 * parses settings for configured plugins
	 *
	 * @param SimpleXMLElement $plugins
	 */
	private function parsePlugins(\SimpleXMLElement $plugins) {
		foreach($plugins->plugin as $p) {
			$a = $p->attributes();
			$this->plugins[] = array ('class' => (string) $a->class, 'eventTypes' => preg_split('~\s*,\s*~', (string) $a->listens_to), 'configXML' => $p->asXML());
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

			$pageId	= (string) $a->id;

			if(isset($a->controller)) {
				$parameters['controller'] = (string) $a->controller;
			}

			if(isset($a->auth)) {

				$auth = strtoupper(trim((string) $a->auth));

				if(defined("vxPHP\\User\\UserAbstract::AUTH_$auth")) {
					$auth = constant("vxPHP\\User\\UserAbstract::AUTH_$auth");

					if(isset($a->auth_parameters)) {
						$parameters['authParameters'] = trim((string) $a->auth_parameters);
					}
				}
				else {
					$auth = -1;
				}

				$parameters['auth'] = $auth;
			}


			$this->routes[$scriptName][$pageId] = new Route($pageId, $scriptName, $parameters);

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

			$auth = strtoupper(trim((string) $a->auth));
			if(defined("vxPHP\\User\\UserAbstract::AUTH_$auth")) {
				$m->setAuth(constant("vxPHP\\User\\UserAbstract::AUTH_$auth"));

				if(isset($a->auth_parameters)) {
					$m->setAuthParameters((string) $a->auth_parameters);
				}
			}
			else {
				$m->setAuth(-1);
			}
		}

		foreach($menu->children() as $entry) {
			if($entry->getName() == 'menuentry') {
				$a = $entry->attributes();

				if(isset($a->page)) {
					$page = (string) $a->page;
					$local = strpos($page, '/') !== 0 && !preg_match('~^[a-z]+://~', $page);

					if(!$local || isset($this->routes[$root][$page]) || isset($this->routes[$root]['default'])) {

						$e = new MenuEntry((string) $a->page, $a, $local);

						if(isset($a->auth)) {

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

						$m->appendEntry($e);

						if(isset($entry->menu)) {
							$e->appendMenu($this->parseMenu($entry->menu));
						}
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
	 * attaches all in config file declared event listeners
	 */
	public function attachPlugins() {
		foreach($this->plugins as $plugin) {
			foreach($plugin['eventTypes'] as $eventType) {
				$pluginInstance = new $plugin['class'];

				if(method_exists($pluginInstance, 'configure')) {
					$pluginInstance->configure(simplexml_load_string($plugin['configXML']));
				}

				EventDispatcher::getInstance()->attach($pluginInstance, $eventType);
			}
		}
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

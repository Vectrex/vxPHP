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
 * creates a configuration singleton by parsing an XML configuration
 * file
 *
 * @version 2.0.0 2017-05-04
 */
class Config {

	/**
	 * @var \stdClass
	 */
	public	$site;

	/**
	 * db settings
	 * will be replaced by vxpdo settings
	 *
	 * @deprecated
	 * @var \stdClass
	 */
	public	$db;

	/**
	 * vxpdo settings
	 *
	 * @var array
	 */
	public $vxpdo;

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
	 * holds sections of config file which are parsed
	 *
 	 * @var array
	 */
	private	$sections	= [];

	/**
	 * a list of already processed XML files
	 * any XML file can only be parsed once
	 * avoids circular references but (currently) also disallows the
	 * re-use of XML "snippets"
	 *
	 * @var array
	 */
	private $parsedXmlFiles = [];
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
		$xmlFile = realpath($xmlFile);

		$previousUseErrors = libxml_use_internal_errors(TRUE);

		$config = new \DOMDocument();

		if(!$config->load($xmlFile, LIBXML_NOCDATA)) {

			$this->dumpXmlErrors($xmlFile);
			exit();

		}

		if($config->firstChild->nodeName !== 'config') {
			
			throw new ConfigException(sprintf("No 'config' root element found in %s.", $xmlFile));

		}

		// recursively add all includes to main document

		$this->includeIncludes($config, $xmlFile);
		$this->parseConfig($config);
		$this->getServerConfig();

		libxml_use_internal_errors($previousUseErrors);

	}

	/**
	 * Create formatted output of XML errors
	 *
	 * @param string $xmlFile
	 * @throws ConfigException
	 */
	private function dumpXmlErrors($xmlFile) {

		$severity = [LIBXML_ERR_WARNING => 'Warning', LIBXML_ERR_ERROR => 'Error', LIBXML_ERR_FATAL => 'Fatal'];
		$errors = [];

		foreach(libxml_get_errors() as $error) {
			$errors[] = sprintf("Row %d, column %d: %s (%d) %s", $error->line, $error->column, $severity[$error->level], $error->code, $error->message);
		}

		throw new ConfigException(sprintf("Could not parse XML configuration in '%s'.\n\n%s", $xmlFile, implode("\n", $errors)));

	}

	/**
	 * recursively include XML files in include tags
	 * to avoid circular references any file can only be included once
	 *
	 * @param \DOMDocument $doc
	 * @param string $filepath
	 * @throws ConfigException
	 */
	private function includeIncludes(\DOMDocument $doc, $filepath) {

		$path = rtrim(dirname(realpath($filepath)), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		if(in_array($filepath, $this->parsedXmlFiles)) {

			throw new ConfigException(sprintf("File %s has already been used.", $filepath));

		}

		$this->parsedXmlFiles[] = $filepath;

		$includes = (new \DOMXPath($doc))->query('//include');

		foreach($includes as $node) {

			if(!empty($node->nodeValue)) {

				// load file

				$include = new \DOMDocument();

				if(!$include->load($path . $node->nodeValue, LIBXML_NOCDATA)) {

					$this->dumpXmlErrors($include);
					exit();

				}

				//  recursively insert includes

				$this->includeIncludes($include, $path . $node->nodeValue);

				// import root node and descendants of include
				
				$importedNode = $doc->importNode($include->firstChild, TRUE);
				
				// check whether included file groups several elements under a config element

				if($importedNode->nodeName !== 'config') {

					// replace include element with imported root element

					$node->parentNode->replaceChild($importedNode, $node);

				}

				else {
					
					// append all child elements of imported root element
					
					while($importedNode->firstChild) {
						
						$node->parentNode->insertBefore($importedNode->firstChild, $node);
						
					}

					// delete include element
					
					$node->parentNode->removeChild($node);

				}
			}
		}
	}

	/**
	 * iterates through the top level nodes (sections) of the config
	 * file; if a method matching the section name is found this method
	 * is called to parse the section
	 *
	 * @param \DOMDocument $config
	 * @throws ConfigException
	 * @return void
	 */
	private function parseConfig(\DOMDocument $config) {

		try {

			// determine server context, missing SERVER_ADDR assumes localhost/CLI

			$this->isLocalhost = !isset($_SERVER['SERVER_ADDR']) || !!preg_match('/^(?:127|192|1|0)(?:\.\d{1,3}){3}$/', $_SERVER['SERVER_ADDR']);


			$rootNode = $config->firstChild;

			if($rootNode->nodeName !== 'config') {

				throw new ConfigException("No root element 'config' found.");

			}

			// allow parsing of specific sections

			foreach($rootNode->childNodes as $section) {

				if($section->nodeType !== XML_ELEMENT_NODE) {

					continue;

				}

				$sectionName = $section->nodeName;

				if(empty($this->sections) || in_array($sectionName, $this->sections)) {

					$methodName =
						'parse' .
						ucfirst(preg_replace_callback('/_([a-z])/', function($match) { return strtoupper($match[1]); }, $sectionName)) .
						'Settings'
					;

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
	 * @deprecated will be replaced by datasource settings
	 *
	 * @param DOMNode $db
	 */
	private function parseDbSettings(\DOMNode $db) {

		$context = $this->isLocalhost ? 'local' : 'remote';
		$xpath = new \DOMXPath($db->ownerDocument);

		$d = $xpath->query("db_connection[@context='$context']", $db);

		if(!count($d)) {
			$d = $xpath->query('db_connection');
		}

		if(count($d)) {
			$this->db = new \stdClass();

			foreach($d->item(0)->childNodes as $node) {

				if($node->nodeType !== XML_ELEMENT_NODE) {
					continue;
				}

				$v = trim($node->nodeValue);
				$k = $node->nodeName;

				$this->db->$k = $v;

			}
		}

	}

	/**
	 * parse datasource settings
	 *
	 * @param \DOMNode $datasources
	 * @throws ConfigException
	 */
	private function parseVxpdoSettings(\DOMNode $vxpdo) {

		if(is_null($this->vxpdo)) {
			$this->vxpdo = [];
		}

		foreach($vxpdo->getElementsByTagName('datasource') as $datasource) {

			$name = $datasource->getAttribute('name') ?: 'default';

			if(array_key_exists($name,  $this->vxpdo)) {
				throw new ConfigException(sprintf("Datasource '%s' declared twice.", $name));
			}

			$config = [
				'driver' => NULL,
				'dsn' => NULL,
				'host' => NULL,
				'port' => NULL,
				'user' => NULL,
				'password' => NULL,
				'dbname' => NULL,
			];

			foreach($datasource->childNodes as $node) {

				if($node->nodeType !== XML_ELEMENT_NODE) {
					continue;
				}

				if(array_key_exists($node->nodeName, $config)) {
					$config[$node->nodeName] = trim($node->nodeValue);
				}
			}

			if(is_null($config['driver'])) {
				throw new ConfigException(sprintf("No driver defined for datasource '%s'.", $name));
			}

			$this->vxpdo[$name] = (object) $config;

		}

	}

	/**
	 * @todo
	 *
	 * @param \DOMNode $propel
	 * @throws ConfigException
	 */
	private function parsePropelSettings(\DOMNode $propel) {

		if(!class_exists('\\PropelConfiguration')) {
			throw new ConfigException("Class 'PropelConfiguration' not found.");
		}

		var_dump(json_decode(json_encode((array) $propel)), TRUE);

	}

	/**
	 * parses all (optional) mail settings
	 *
	 * @param \DOMNode $mail
	 * @throws ConfigException
	 */
	private function parseMailSettings(\DOMNode $mail) {

		if(($mailer = $mail->getElementsByTagName('mailer')->item(0))) {

			if(is_null($this->mail)) {
				$this->mail = new \stdClass();
				$this->mail->mailer = new \stdClass();
			}


			if(!($class = $mailer->getAttribute('class'))) {
				throw new ConfigException('No mailer class specified.');
			}

			$this->mail->mailer->class = $class;

			foreach($mailer->childNodes as $node) {

				if($node->nodeType !== XML_ELEMENT_NODE) {
					continue;
				}

				$this->mail->mailer->{$node->nodeName} = trim($node->nodeValue);
			}
		}

	}

	/**
	 * parse settings for binaries
	 * @todo clean up code
	 *
	 * @param \DOMNode $binaries
	 * @throws ConfigException
	 */
	private function parseBinariesSettings(\DOMNode $binaries) {

		$context = $this->isLocalhost ? 'local' : 'remote';

		$xpath = new \DOMXPath($binaries->ownerDocument);

		$e = $xpath->query("db_connection[@context='$context']", $binaries);

		if(!count($e)) {
			$e = $xpath->query('executables');
		}

		if(count($e)) {

			$p = $e->item(0)->getElementsByTagName('path');

			if(empty($p)) {
				throw new ConfigException('Malformed "site.ini.xml"! Missing path for binaries.');
			}

			$this->binaries = new \stdClass;
			$this->binaries->path = rtrim($p->item(0)->nodeValue, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

			foreach($e->item(0)->getElementsByTagName('executable') as $v) {

				if(!($id = $v->getAttribute('id'))) {
					throw new ConfigException('Binary without id found.');
				}

				foreach($v->attributes as $attr) {
					$this->binaries->executables[$id][$attr->nodeName] = $attr->nodeValue;
				}
			}
		}

	}

	/**
	 * parse general website settings
	 *
	 * @param \DOMNode $site
	 */
	private function parseSiteSettings(\DOMNode $site) {

		if(is_null($this->site)) {

			$this->site = new \stdClass;
			$this->site->use_nice_uris = FALSE;

		}

		foreach($site->childNodes as $node) {

			if($node->nodeType !== XML_ELEMENT_NODE) {
				continue;
			}

			$v = trim($node->nodeValue);
			$k = $node->nodeName;

			switch ($k) {

				case 'locales':
					if(!isset($this->site->locales)) {
						$this->site->locales = [];
					}

					foreach($node->getElementsByTagName('locale') as $locale) {
						$loc = $locale->getAttribute('value');
						if($loc && !in_array($loc, $this->site->locales)) {
							$this->site->locales[] = $loc;
						}
						if($loc && $locale->getAttribute('default') === '1') {
							$this->site->default_locale = $loc;
						}
					}

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

	}

	/**
	 * parse templating configuration
	 * currently only filters for SimpleTemplate templates and their configuration are parsed
	 *
	 * @param \DOMNode $templating
	 */
	private function parseTemplatingSettings(\DOMNode $templating) {

		if(is_null($this->templating)) {
			$this->templating = new \stdClass;
			$this->templating->filters = [];
		}

		$xpath = new \DOMXPath($templating->ownerDocument);

		$context = $this->isLocalhost ? 'local' : 'remote';
		$filters = $xpath->query("filters/filter[@context='$context']", $templating);

		foreach($filters as $filter) {

			$id = $filter->getAttribute('id');
			$class = $filter->getAttribute('class');

			if(!$id) {
				throw new ConfigException('Templating filter without id found.');
			}

			if(!$class)	{
				throw new ConfigException(sprintf("No class for templating filter '%s' configured.", $id));
			}

			if(isset($this->templating->filters[$id])) {
				throw new ConfigException(sprintf("Templating filter '%s' has already been defined.", $id));
			}

			// clean path delimiters, prepend leading backslash, and replace slashes with backslashes

			$class = '\\' . ltrim(str_replace('/', '\\', $class), '/\\');

			// store parsed information

			$this->templating->filters[$id] = [
				'class' => $class,
				'parameters' => $filter->getAttribute('parameters')
			];

		}

	}

	/**
	 * parse various path setting
	 *
	 * @param \DOMNode $paths
	 */
	private function parsePathsSettings(\DOMNode $paths) {

		if(is_null($this->paths)) {
			$this->paths = [];
		}

		foreach($paths->getElementsByTagName('path') as $path) {

			$id = $path->getAttribute('id');
			$subdir = $path->getAttribute('subdir') ?: '';

			if(!$id || !$subdir) {
				continue;
			}

			$subdir = '/' . trim($subdir, '/') . '/';

			// additional attributes are currently ignored

			$this->paths[$id] = ['subdir' => $subdir];

		}

	}

	/**
	 * parse page routes
	 * called seperately for differing script attributes
	 *
	 * @param \DOMNode $pages
	 * @throws ConfigException
	 */
	private function parsePagesSettings(\DOMNode $pages) {

		$scriptName = $pages->getAttribute('script');

		if(!$scriptName) {
			if($this->site) {
				$scriptName = $this->site->root_document ?: 'index.php';
			}
			else {
				$scriptName = 'index.php';
			}
		}

		$redirect = $pages->getAttribute('default_redirect');

		if(is_null($this->routes)) {
			$this->routes = [];
		}

		if(!array_key_exists($scriptName, $this->routes)) {
			$this->routes[$scriptName] = [];
		}

		foreach($pages->getElementsByTagName('page') as $page) {

			$parameters = [
				'redirect' => $redirect
			];

			// get route id

			$pageId	= $page->getAttribute('id');

			if(is_null($pageId) || trim($pageId) === '') {
				throw new ConfigException('Route with missing or invalid id found.');
			}

			// read optional controller

			if(($controller = $page->getAttribute('controller'))) {

				// clean path delimiters, prepend leading backslash, replace slashes with backslashes, apply ucfirst to all namespaces

				$namespaces = explode('\\', ltrim(str_replace('/', '\\', $controller), '/\\'));

				if(count($namespaces) && $namespaces[0]) {
					$parameters['controller'] = '\\Controller\\'. implode('\\', array_map('ucfirst', $namespaces)) . 'Controller';
				}

				else {
					throw new ConfigException(sprintf("Controller string '%s' cannot be parsed.", (string) $controller));
				}
			}

			// read optional controller method

			if(($method = $page->getAttribute('method'))) {
				$parameters['method'] = $method;
			}

			// read optional allowed request methods

			if(($requestMethods = $page->getAttribute('request_methods'))) {
				$allowedMethods	= 'GET POST PUT DELETE';
				$requestMethods	= preg_split('~\s*,\s*~', strtoupper($requestMethods));

				foreach($requestMethods as $requestMethod) {
					if(strpos($allowedMethods, $requestMethod) === -1) {
						throw new ConfigException(sprintf("Invalid request method '%s' for route '%s'.", $requestMethod, $pageId));
					}
				}
				$parameters['requestMethods'] = $requestMethods;
			}

			// when no path is defined page id will be used for route lookup

			if(($path = $page->getAttribute('path'))) {

				// initialize lookup expression

				$rex = $path;

				// extract route parameters and default values

				if(preg_match_all('~\{(.*?)(=.*?)?\}~', $path, $matches)) {

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

				$parameters['path'] = $path;

			}

			else {
				$rex = $pageId;
			}

			$parameters['match'] = $rex;

			// extract optional authentication requirements

			if(($auth = $page->getAttribute('auth'))) {

				$auth = strtolower(trim($auth));

				if($auth && ($authParameters = $page->getAttribute('auth_parameters'))) {
					$parameters['authParameters'] = trim($authParameters);
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
	 * @param \DOMNode $menus
	 */
	private function parseMenusSettings(\DOMNode $menus) {

		foreach ($menus->getElementsByTagName('menu') as $menu) {

			$menuInstance = $this->parseMenu($menu);
			$this->menus[$menuInstance->getId()] = $menuInstance;

		}

	}

	/**
	 * parse settings for services
	 * only service id, class and parameters are parsed
	 * lazy initialization is handled by Application instance
	 *
	 * @param \DOMNode $services
	 * @throws ConfigException
	 */
	private function parseServicesSettings(\DOMNode $services) {

		if(is_null($this->services)) {
			$this->services = [];
		}

		foreach($services->getElementsByTagName('service') as $service) {

			if(!($id = $service->getAttribute('id'))) {
				throw new ConfigException('Service without id found.');
			}

			if(isset($this->services[$id])) {
				throw new ConfigException(sprintf("Service '%s' has already been defined.", $id));
			}

			if(!($class = $service->getAttribute('class'))) {
				throw new ConfigException(sprintf("No class for service '%s' configured.", $id));
			}

			// clean path delimiters, prepend leading backslash, and replace slashes with backslashes

			$class = '\\' . ltrim(str_replace('/', '\\', $class), '/\\');

			// store parsed information

			$this->services[$id] = [
				'class'			=> $class,
				'parameters'	=> []
			];

			foreach($service->getElementsByTagName('parameter') as $parameter) {

				$name = $parameter->getAttribute('name');
				$value = $parameter->getAttribute('value');

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
	 * @param \DOMNode $plugins
	 */
	private function parsePluginsSettings(\DOMNode $plugins) {

		if(is_null($this->services)) {
			$this->services = [];
		}

		foreach($plugins->getElementsByTagName('plugin') as $plugin) {

			if(!($id = $plugin->getAttribute('id'))) {
				throw new ConfigException('Plugin without id found.');
			}

			if(isset($this->plugins[$id])) {
				throw new ConfigException(sprintf("Plugin '%s' has already been defined.", $id));
			}

			if(!($class = $plugin->getAttribute('class'))) {
				throw new ConfigException(sprintf("No class for plugin '%s' configured.", $id));
			}

			// clean path delimiters, prepend leading backslash, and replace slashes with backslashes

			$class = '\\' . ltrim(str_replace('/', '\\', $class), '/\\');

			// store parsed information

			$this->plugins[$id] = [
				'class'			=> $class,
				'parameters'	=> []
			];

			foreach($plugin->getElementsByTagName('parameter') as $parameter) {

				$name = $parameter->getAttribute('name');
				$value = $parameter->getAttribute('value');

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
	 * @param \DOMNode $menu
	 * @return Menu
	 */
	private function parseMenu(\DOMNode $menu) {

		$root = $menu->getAttribute('script');

		if(!$root) {
			if($this->site) {
				$root= $this->site->root_document ?: 'index.php';
			}
			else {
				$root= 'index.php';
			}
		}

		$type = $menu->getAttribute('type') === 'dynamic' ? 'dynamic' : 'static';
		$service = $menu->getAttribute('service') ?: NULL;
		$id = $menu->getAttribute('id') ?: NULL;

		if($type === 'dynamic' && !$service) {
			throw new ConfigException("A dynamic menu requires a configured service.");
		}

		$m = new Menu(
			$root,
			$id,
			$type,
			$service
		);

		if(($menuAuth = strtolower(trim($menu->getAttribute('auth'))))) {

			$m->setAuth($menuAuth);

			// if an auth level is defined, additional authentication parameters can be set

			if(($authParameters = $menu->getAttribute('auth_parameters'))) {
				$m->setAuthParameters($authParameters);
			}

		}

		foreach($menu->childNodes as $entry) {

			if($entry->nodeType !== XML_ELEMENT_NODE) {
				continue;
			}

			// read additional attributes which are passed to menu entry constructor

			$attributes = [];
			foreach($entry->attributes as $attr) {

				$nodeName = $attr->nodeName;

				if(
					$nodeName !== 'page' &&
					$nodeName !== 'path' &&
					$nodeName !== 'auth' &&
					$nodeName !== 'auth_parameters'
				) {
					$attributes[$attr->nodeName] = $attr->nodeValue;
				}
			}

			if($entry->nodeName === 'menuentry') {

				$page = $entry->getAttribute('page');
				$path = $entry->getAttribute('path');

				if($page && $path) {

					throw new ConfigException(sprintf("Menu entry with both page ('%s') and path ('%s') attribute found.", $page, $path));

				}

				// menu entry comes with a path attribute (which can also link an external resource)

				if($path) {

					$local = strpos($path, '/') !== 0 && !preg_match('~^[a-z]+://~', $path);

					$e = new MenuEntry($path, $attributes, $local);

				}

				// menu entry comes with a page attribute, in this case the route path is used

				else if($page) {

					if(!isset($this->routes[$m->getScript()][$page])) {

						throw new ConfigException(sprintf(
							"No route for menu entry ('%s') found. Available routes for script '%s' are '%s'.",
							$page,
							$m->getScript(),
							empty($this->routes[$m->getScript()]) ? 'none' : implode("', '", array_keys($this->routes[$m->getScript()]))
						));

					}

					$e = new MenuEntry((string) $this->routes[$m->getScript()][$page]->getPath(NULL, TRUE), $attributes, TRUE);

				}

				// handle authentication settings of menu entry

				if(($auth = strtolower(trim($entry->getAttribute('auth'))))) {

					// set optional authentication level

					$e->setAuth($auth);

					// if auth level is defined, additional authentication parameters can be set

					if(($authParameters = $entry->getAttribute('auth_parameters'))) {
						$e->setAuthParameters($authParameters);
					}

				}

				$m->appendEntry($e);

				if(isset($entry->menu)) {
					$e->appendMenu($this->parseMenu($entry->menu));
				}

			}

			else if($entry->nodeName === 'menuentry_placeholder') {

				$e = new DynamicMenuEntry(NULL, $attributes);
				$m->appendEntry($e);

			}
		}

		return $m;

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

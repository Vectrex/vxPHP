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

use DOMDocument;
use DOMNode;
use stdClass;
use vxPHP\Application\Config\Parser\Xml\MailSettings;
use vxPHP\Application\Config\Parser\Xml\PluginsSettings;
use vxPHP\Application\Config\Parser\Xml\ServicesSettings;
use vxPHP\Application\Config\Parser\Xml\SiteSettings;
use vxPHP\Application\Config\Parser\Xml\TemplatingSettings;
use vxPHP\Application\Config\Parser\Xml\VxpdoSettings;
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
 * @version 2.2.0 2020-07-04
 */
class Config {

	/**
	 * @var stdClass
	 */
	public	$site;

	/**
	 * db settings
	 * will be replaced by vxpdo settings
	 *
	 * @deprecated
	 * @var stdClass
	 */
	public	$db;

	/**
	 * vxpdo settings
	 *
	 * @var array
	 */
	public $vxpdo;

	/**
	 * @var stdClass
	 */
	public	$mail;

	/**
	 * @var stdClass
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
	 * @var stdClass
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
	public function __construct($xmlFile, array $sections = [])
    {
		$this->sections	= $sections;
		$xmlFile = realpath($xmlFile);

		$previousUseErrors = libxml_use_internal_errors(true);

		$config = new DOMDocument();

		if(!$config->load($xmlFile, LIBXML_NOCDATA)) {

			$this->dumpXmlErrors($xmlFile);
			exit();

		}

		// skip any comments

		while($config->firstChild instanceof \DOMComment) {
			$config->removeChild($config->firstChild);
		}

		if('config' !== $config->firstChild->nodeName) {
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
	private function dumpXmlErrors($xmlFile): void
    {
		$severity = [LIBXML_ERR_WARNING => 'Warning', LIBXML_ERR_ERROR => 'Error', LIBXML_ERR_FATAL => 'Fatal'];
		$errors = [];

		foreach(libxml_get_errors() as $error) {
			$errors[] = sprintf('Row %d, column %d: %s (%d) %s', $error->line, $error->column, $severity[$error->level], $error->code, $error->message);
		}

		throw new ConfigException(sprintf("Could not parse XML configuration in '%s'.\n\n%s", $xmlFile, implode("\n", $errors)));
	}

	/**
	 * recursively include XML files in include tags
	 * to avoid circular references any file can only be included once
	 *
	 * @param DOMDocument $doc
	 * @param string $filepath
	 * @throws ConfigException
	 */
	private function includeIncludes(DOMDocument $doc, $filepath): void
    {
		$path = rtrim(dirname(realpath($filepath)), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		if(in_array($filepath, $this->parsedXmlFiles, true)) {
			throw new ConfigException(sprintf('File %s has already been used.', $filepath));
		}

		$this->parsedXmlFiles[] = $filepath;

		$includes = (new \DOMXPath($doc))->query('//include');

		foreach($includes as $node) {

			if(!empty($node->nodeValue)) {

				// load file

				$include = new DOMDocument();

				if(!$include->load($path . $node->nodeValue, LIBXML_NOCDATA)) {

					$this->dumpXmlErrors($path . $node->nodeValue);
					exit();

				}

				//  recursively insert includes

				$this->includeIncludes($include, $path . $node->nodeValue);

				// import root node and descendants of include
				
				foreach($include->childNodes as $childNode) {

					if($childNode instanceOf \DOMComment) {
						continue;
					}

					$importedNode = $doc->importNode($childNode, true);
					break;

				}
				
				// check whether included file groups several elements under a config element

				if('config' !== $importedNode->nodeName) {

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
	 * @param DOMDocument $config
	 * @throws ConfigException
	 * @return void
	 */
	private function parseConfig(DOMDocument $config): void
    {
		try {

			// determine server context, missing SERVER_ADDR assumes localhost/CLI

			$this->isLocalhost = !isset($_SERVER['SERVER_ADDR']) || (bool) preg_match('/^(?:127|192|1|0)(?:\.\d{1,3}){3}$/', $_SERVER['SERVER_ADDR']);

			$rootNode = $config->firstChild;

			// allow parsing of specific sections

			foreach($rootNode->childNodes as $section) {

				if($section->nodeType !== XML_ELEMENT_NODE) {

					continue;

				}

				$sectionName = $section->nodeName;

				if(empty($this->sections) || in_array($sectionName, $this->sections, true)) {

					$methodName =
						'parse' .
						ucfirst(preg_replace_callback('/_([a-z])/', function($match) { return strtoupper($match[1]); }, $sectionName)) .
						'Settings'
					;

					if(method_exists($this, $methodName)) {
						$this->$methodName($section);
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
	private function parseDbSettings(DOMNode $db): void
    {
		$context = $this->isLocalhost ? 'local' : 'remote';
		$xpath = new \DOMXPath($db->ownerDocument);

		$d = $xpath->query("db_connection[@context='$context']", $db);
		
		if(!$d->length) {
			$d = $db->getElementsByTagName('db_connection');
		}

		if($d->length) {
			$this->db = new stdClass();

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
	 * @param DOMNode $vxpdo
	 * @throws ConfigException
	 */
	private function parseVxpdoSettings(DOMNode $vxpdo): void
    {
        $this->vxpdo = (new VxpdoSettings())->parse($vxpdo);
	}

	/**
	 * parses all (optional) mail settings
	 *
	 * @param DOMNode $mail
	 * @throws ConfigException
	 */
	private function parseMailSettings(DOMNode $mail): void
    {
        $this->mail = (new MailSettings())->parse($mail);
	}

	/**
	 * parse settings for binaries
	 *
	 * @param DOMNode $binaries
	 * @throws ConfigException
	 */
	private function parseBinariesSettings(DOMNode $binaries): void
    {
		$context = $this->isLocalhost ? 'local' : 'remote';

		$xpath = new \DOMXPath($binaries->ownerDocument);

		$e = $xpath->query("executables[@context='$context']", $binaries);

		if(!$e->length) {
			$e = $xpath->query('executables', $binaries);
		}

		if($e->length) {

			$p = $e->item(0)->getElementsByTagName('path');

			if(empty($p)) {
				throw new ConfigException('Malformed "site.ini.xml"! Missing path for binaries.');
			}

			$this->binaries = new stdClass;
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
	 * @param DOMNode $site
	 */
	private function parseSiteSettings(DOMNode $site): void
    {
        $this->site = (new SiteSettings())->parse($site);
	}

    /**
     * parse templating configuration
     * currently only filters for SimpleTemplate templates and their configuration are parsed
     *
     * @param DOMNode $templating
     */
	private function parseTemplatingSettings(DOMNode $templating): void
    {
        $this->templating = (new TemplatingSettings())->parse($templating);
	}

	/**
	 * parse various path setting
	 *
	 * @param DOMNode $paths
	 */
	private function parsePathsSettings(DOMNode $paths): void
    {
		if($this->paths === null) {
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
	 * @param DOMNode $pages
	 * @throws ConfigException
	 */
	private function parsePagesSettings(DOMNode $pages): void
    {
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

		if($this->routes === null) {
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

			if($pageId === null || trim($pageId) === '') {
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
				$allowedMethods	= Route::KNOWN_REQUEST_METHODS;
				$requestMethods	= preg_split('~\s*,\s*~', strtoupper($requestMethods));

				foreach($requestMethods as $requestMethod) {
					if(!in_array($requestMethod, $allowedMethods, true)) {
						throw new ConfigException(sprintf("Invalid request method '%s' for route '%s'.", $requestMethod, $pageId));
					}
				}
				$parameters['requestMethods'] = $requestMethods;
			}

			if(($path = $page->getAttribute('path'))) {
				$parameters['path'] = $path;
			}

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
     * if menus share the same id entries of later menus are appended
     * to the first; other menu attributes are left unchanged
     *
     *
     * @param DOMNode $menus
     * @throws ConfigException
     */
	private function parseMenusSettings(DOMNode $menus): void
    {
		foreach ((new \DOMXPath($menus->ownerDocument))->query('menu', $menus) as $menu) {

			$id = $menu->getAttribute('id') ?: Menu::DEFAULT_ID;

			if(isset($this->menus[$id])) {
				$this->appendMenuEntries($menu->childNodes, $this->menus[$id]);
			}
			else {
				$this->menus[$id] = $this->parseMenu($menu);
			}
		}
	}

	/**
	 * parse settings for services
	 * only service id, class and parameters are parsed
	 * lazy initialization is handled by Application instance
	 *
	 * @param DOMNode $services
	 * @throws ConfigException
	 */
	private function parseServicesSettings(DOMNode $services): void
    {
        $this->services = (new ServicesSettings())->parse($services);
	}

    /**
     * parses settings for plugins
     * plugin id, class, events and parameters are parsed
     * initialization (not lazy) is handled by Application instance
     *
     * @param DOMNode $plugins
     * @throws ConfigException
     */
	private function parsePluginsSettings(DOMNode $plugins): void
    {
        $this->plugins = (new PluginsSettings())->parse($plugins);
	}

    /**
     * Parse XML menu entries and creates menu instance
     *
     * @param DOMNode $menu
     * @return Menu
     * @throws ConfigException
     */
	private function parseMenu(DOMNode $menu): Menu
    {
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
		$service = $menu->getAttribute('service') ?: null;
		$id = $menu->getAttribute('id') ?: null;

		if($type === 'dynamic' && !$service) {
			throw new ConfigException('A dynamic menu requires a configured service.');
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

		$this->appendMenuEntries($menu->childNodes, $m);

		return $m;
	}

    /**
     * append menu entries of configuration to a
     * previously created Menu instance
     *
     * @param \DOMNodeList $entries
     * @param Menu $menu
     * @throws ConfigException
     */
	private function appendMenuEntries(\DOMNodeList $entries, Menu $menu): void
    {
		foreach($entries as $entry) {

			if($entry->nodeType !== XML_ELEMENT_NODE || 'menuentry' !== $entry->nodeName) {
				continue;
			}

			// read additional attributes which are passed to menu entry constructor

			$attributes = [];

			foreach($entry->attributes as $attr) {

				$nodeName = $attr->nodeName;

				if(!in_array($nodeName, ['page', 'path', 'auth', 'auth_parameters'])) {
					$attributes[$attr->nodeName] = $attr->nodeValue;
				}

			}

			if('menuentry' === $entry->nodeName) {

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

					if(!isset($this->routes[$menu->getScript()][$page])) {

						throw new ConfigException(sprintf(
							"No route for menu entry ('%s') found. Available routes for script '%s' are '%s'.",
							$page,
							$menu->getScript(),
							empty($this->routes[$menu->getScript()]) ? 'none' : implode("', '", array_keys($this->routes[$menu->getScript()]))
						));

					}

					$e = new MenuEntry((string) $this->routes[$menu->getScript()][$page]->getPath(), $attributes, true);

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

				$menu->appendEntry($e);

				$submenu = (new \DOMXPath($entry->ownerDocument))->query('menu', $entry); 

				if($submenu->length) {
					$e->appendMenu($this->parseMenu($submenu->item(0)));
				}

			}

			else if($entry->nodeName === 'menuentry_placeholder') {

				$e = new DynamicMenuEntry(null, $attributes);
				$menu->appendEntry($e);

			}
		}
	}

	/**
	 * create constants for simple access to certain configuration settings
	 */
	public function createConst(): void
    {
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
	 * @return array
	 */
	public function getPaths($access = 'rw'): array
    {
		$paths = [];
		foreach($this->paths as $p) {
			if($p['access'] === $access) {
				$paths[] = $p;
			}
		}
		return $paths;
	}

	/**
	 * add particular information regarding server configuration, like PHP extensions
	 */
	private function getServerConfig(): void
    {
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

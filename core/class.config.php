<?php
/**
 * Config
 * creates configuration singleton by parsing XML ini-file
 * 
 * @version 0.8.0 2013-01-18
 * 
 * @todo refresh() method
 */
class Config {

	public	$site,
			$db,
			$mail,
			$paths,
			$binaries,
			$uploadImages,
			$pages,
			$wildcardPages,
			$menus,
			$server;

	/**
	 * takes up all $_GET parameters,
	 * either copied directly or after parsing a nice_uri
	 */
	public $_get = array();

	public $locales =  array(
		'de' => array('de', 'de_DE', 'deu_deu'),
		'en' => array('en', 'en_GB', 'en_US', 'eng')
	);

	private static $instance;

	private		$isLocalhost,
				$xmlFile,
				$xmlFileTS,
				$sections = array(),
				$config,
				$document,
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
		$this->setLocale();
		$this->getServerConfig();

		unset($this->config);
	}

	private function __clone() {}

	public static function getInstance($xmlFile, array $sections = array()) {
		if(
			false && 
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

	private function parseConfig() {

		$this->pages			= array();
		$this->wildcardPages	= array();

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
	private function parseDbSettings(SimpleXMLElement $db) {

		$context = $this->isLocalhost ? 'local' : 'remote';

		$d = $db->xpath("db_connection[@context='$context']");
		
		if(empty($d)) {
			$d = $db->xpath('db_connection');
		}

		if(!empty($d)) {
			$this->db = new stdClass;
		
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
	private function parseMailSettings(SimpleXMLElement $mail) {
		if(!empty($mail->mailer[0])) {

			$mailer = $mail->mailer[0];

			$this->mail = new stdClass();
			$this->mail->mailer = new stdClass();

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
	private function parseBinariesSettings(SimpleXmlElement $binaries) {

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
		
			$this->binaries = new stdClass;
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
	private function parseSiteSettings(SimpleXMLElement $site) {

		$this->site = new stdClass;

		foreach($site[0] as $k => $v) {
			if($k != 'locales') {
				$this->site->$k = trim((string) $v);
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
	 * parse various upload parameter settings
	 * 
	 * @param SimpleXMLElement $uploadParameters
	 */
	private function parseUploadParametersSettings(SimpleXMLElement $uploadParameters) {
		
		if(isset($uploadParameters->images->group)) {
			foreach($uploadParameters->images->group as $g) {
				$id = (string) $g->attributes()->id;
				$this->uploadImages[$id]->allowedTypes = explode('|', strtolower((string) $g->attributes()->types));
				$this->uploadImages[$id]->sizes = array();
		
				foreach($g->size as $s) {
					$a = $s->attributes();
					$o = new stdClass;
		
					if((string) $a->width === 'original' || (string) $a->height === 'original') {
						$o->width = 'original';
						$o->height = 'original';
					}
					else {
						$o->width	= (int) $a->width;
						$o->height	= (int) $a->height;
					}
					// @TODO clean up path issues
					$o->path = trim((string) $a->path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
					$this->uploadImages[$id]->sizes[] = $o;
				}
			}
		}
	}
	
	/**
	 * parse various path setting
	 * 
	 * @param SimpleXMLElement $paths
	 */
	private function parsePathsSettings(SimpleXMLElement $paths) {
		
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
	private function parsePlugins(SimpleXMLElement $plugins) {
		foreach($plugins->plugin as $p) {
			$a = $p->attributes();
			$this->plugins[] = array ('class' => (string) $a->class, 'eventTypes' => preg_split('~\s*,\s*~', (string) $a->listens_to), 'configXML' => $p->asXML());
		}
	}

	/**
	 * parse page settings
	 * called seperately for differing script attributes
	 * 
	 * @param SimpleXMLElement $pages
	 */
	private function parsePagesSettings(SimpleXMLElement $pages) {

		$doc = empty($pages->attributes()->script) ? $this->site->root_document : (string) $pages->attributes()->script;
		$red = empty($pages->attributes()->default_redirect) ? NULL : (string) $pages->attributes()->default_redirect;

		foreach($pages->page as $page) {

			$a = $page->attributes();
			$id = (string) $a->id;

			// so-called wildcard pages end up in their own collection

			if(substr($id, -1) == '*') {
				$id = substr($id, 0, -1);
				$collection = &$this->wildcardPages;
			}

			// otherwise we have standard pages

			else {
				$collection = &$this->pages;
			}

			// attributes stay the same

			$collection[$doc][$id] = new stdClass;
			$collection[$doc][$id]->class = (string) $a->class;
			$collection[$doc][$id]->defaultRedirect = $red;

			// set optional authentication level; if level is not defined, page is locked for everyone
	
			if(isset($a->auth)) {
				$auth = strtoupper(trim((string) $a->auth));
				if(defined("UserAbstract::AUTH_$auth")) {
					$collection[$doc][$id]->auth = constant("UserAbstract::AUTH_$auth");
					
					if(isset($a->auth_parameters)) {
						$collection[$doc][$id]->auth_parameters = trim((string) $a->auth_parameters);
					}
				}
				else {
					$collection[$doc][$id]->auth = -1;
				}
			}
		}
	}

	/**
	 * parse menu tree
	 * 
	 * @param SimpleXMLElement $menus
	 */
	private function parseMenusSettings(SimpleXMLElement $menus) {
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
	private function parseMenu(SimpleXMLElement $menu) {
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
			if(defined("UserAbstract::AUTH_$auth")) {
				$m->setAuth(constant("UserAbstract::AUTH_$auth"));

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

					if(!$local || isset($this->pages[$root][$page]) || isset($this->pages[$root]['default'])) {

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

	// refresh config by re-parsing XML file

	public function refresh() {
	}

	/**
	 * init application
	 * 
	 * sets locale and selects page
	 * @return webpage 
	 */
	public function initPage() {
		if(empty($this->site->use_nice_uris)) {
			$this->site->use_nice_uris = false;
		}
		// check for non-nice URI

		if(!$this->site->use_nice_uris || NiceURI::isPlainURI($_SERVER['REQUEST_URI'])) {
			$this->_get = $_GET;
		}

		else {
			$this->_get = NiceURI::getNiceURI_GET($_SERVER['REQUEST_URI']);
		}

		if(isset($this->_get['lang'])) {
			$this->setLocale($this->_get['lang']);
		}
		return $this->requestPage(!empty($this->_get['page']) ? $this->_get['page'] : NULL, basename($_SERVER['SCRIPT_NAME']));
	}
	
	/**
	 * sets locale of website
	 *
	 * @param string $locale
	 */
	public function setLocale($locale = null) {
		if(!empty($locale) && array_key_exists($locale, $this->locales)) {
			$this->site->current_locale = $locale;
			setlocale(LC_ALL, $this->locales[$locale]);
			return;
		}

		if(!isset($this->site->default_locale) || !isset($this->locales[$this->site->default_locale])) {
			$this->site->default_locale = 'de';
		}
		$this->site->current_locale = $this->site->default_locale;
		setlocale(LC_ALL, $this->locales[$this->site->default_locale]);
	}

	public function createConst() {
		$arr = get_object_vars($this);

		foreach($arr['db'] as $k => $v) {
			if(is_scalar($v)) {
				$k = strtoupper($k);
				if(!defined("DB$k")) { define("DB$k", $v); }
			}
		}

		foreach($arr['site'] as $k => $v) {
			if(is_scalar($v)) {
				$k = strtoupper($k);
				if(!defined($k)) { define($k, $v); }
			}
		}

		foreach($arr['paths'] as $k => $v) {
			$k = strtoupper($k);
			if(!defined($k)) { define($k, $v['subdir']); }
		}
		$locale = localeconv();
		foreach($locale as $k => $v) {
			$k = strtoupper($k);
			if(!defined($k) && !is_array($v)) { define($k, $v); }
		}

		if(!empty($arr['uploadImages'])) {
			foreach($arr['uploadImages'] as $k => $v) {
				$k = strtoupper($k);
				if(!defined("IMG_{$k}_PATH")) {
					define("IMG_{$k}_PATH", $v->sizes[0]->path);
				}
			}
		}
	}

	/**
	 * returns currently used document (e.g. index.php, admin.php)
	 *
	 * @return used document
	 */
	public function getDocument() {
		return $this->document;
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
	 * checks for availability of requested page (considering an optional script file)
	 * fallbacks are either a default page or the first page listed in config file
	 *
	 * @param string $p page id
	 * @param string $doc script name for which page id is searched
	 *
	 * @return string page class name
	 */
	private function requestPage($p, $doc = NULL) {
		if(!isset($doc)) {
			$doc = $this->site->root_document;
		}
	
		// just stored for misc later use
		$this->document = $doc;
	
		// if no page given try to get the first from list
		if(!isset($p) && isset($this->pages[$doc])) {
			$ndx = array_keys($this->pages[$doc]);
			$p = $ndx[0];
		}
	
		// page class for "normal" pages
		if(isset($this->pages[$doc]) && $p !== null && in_array($p ,array_keys($this->pages[$doc]))) {
			return new $this->pages[$doc][$p]->class;
		}
	
		// page class for wildcard pages
		if(isset($this->wildcardPages[$doc]) && $p !== null) {
			foreach(array_keys($this->wildcardPages[$doc]) as $pattern) {
				if(strpos($p, $pattern) === 0) {
					return new $this->wildcardPages[$doc][$pattern]->class;
				}
			}
		}
	
		// default page class, if available
		if(isset($this->pages[$doc]) && in_array('default', array_keys($this->pages[$doc]))) {
			return new $this->pages[$doc]['default']->class;
		}
	
		// first page class assigned to root document
		$ndx = array_keys($this->pages[$this->site->root_document]);
		return new $this->pages[$this->site->root_document][$ndx[0]]->class;
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

class ConfigException extends Exception {
}
?>

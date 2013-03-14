<?php

namespace vxPHP\Webpage;

use vxPHP\Webpage\Exception\WebpageException;
use vxPHP\Webpage\Menu\Menu;
use vxPHP\Webpage\MenuEntry\MenuEntry;

use vxPHP\Template\SimpleTemplate;
use vxPHP\User\UserAbstract;
use vxPHP\User\Admin;
use vxPHP\Util\JSMin;
use vxPHP\Request\NiceURI;
use vxPHP\Template\Util\SimpleTemplateUtil;


/**
 * Parent class for webpages,
 * provides page-independent functionality and fallbacks
 * handles xmlHttpRequests of clients
 *
 * @author Gregor Kofler
 * @version 1.15.2 2012-11-03
 *
 */

abstract class Webpage {

	public		$html,
				$validatedRequests	= array(),
				$currentPage		= NULL,
				$currentDocument	= NULL;
	protected	$allowedRequests	= array('page' => '~^[a-z0-9_]+$~i'),
				$config,
				$pageConfigData,
				$author				= 'Gregor Kofler - Mediendesign und Webapplikationen, http://gregorkofler.com',
				$robots				= 'index, follow',
				$title,
				$keywords,
				$description,
				$css				= array(),
				$js					= array(),
				$compressJS			= FALSE,
				$useTimestamps		= TRUE,
				$metaData			= array(),
				$primedMenus		= array(),	// cache for menus, when shown several times on page
				$forceActiveMenu;

	protected	$db;

	public function __construct() {
		if(!isset($GLOBALS['config'])) {
			throw new WebpageException('Configuraton object not found!');
		}
		if(isset($GLOBALS['db'])) {
			$this->db = &$GLOBALS['db'];
		}
		$this->config = &$GLOBALS['config'];
		$this->html = '';

		$this->currentDocument = $this->config->getDocument();
		$this->allowedRequests += isset($this->pageRequests) ? $this->pageRequests : array();
		$this->validateRequests(array_merge($this->config->_get, $_POST));

		if(isset($this->validatedRequests['page']) && (
			isset($this->config->pages[$this->config->getDocument()][$this->validatedRequests['page']]) ||
			isset($this->config->pages[$this->config->getDocument()]['default']))) {
				$this->currentPage = $this->validatedRequests['page'];
		}
		else if(!empty($this->config->pages)){
			if(empty($this->config->pages[$this->config->getDocument()])) {
				$this->generateHttpError();
				return;
			}
			$pageKeys = array_keys($this->config->pages[$this->config->getDocument()]);
			$this->currentPage = array_shift($pageKeys);
		}
		if(isset($this->config->pages[$this->config->getDocument()][$this->currentPage])) {
			$this->pageConfigData = $this->config->pages[$this->config->getDocument()][$this->currentPage];
		}
		else {
			$this->pageConfigData = $this->config->pages[$this->config->getDocument()]['default'];
		}

		if(!$this->authenticate()) {
			$_SESSION['authViolatingUri'] = !empty($_SERVER['REQUEST_URI']) ? ltrim($_SERVER['REQUEST_URI'], '/') : NULL;
			$this->redirect();
		}
		$this->handleXHR();
	}

	/**
	 * Set value of a single meta element
	 * valid names are: author, description, robots, keywords
	 *
	 * @param string $name of meta tag
	 * @param string $value of tag
	 */
	public function overrideMetaValue($name, $value) {
		if(property_exists($this, $name)) {
			$this->$name = $value;
		}
	}

	/**
	 * add css files for linking in html header
	 *
	 * @param array $css
	 */
	public function appendCssLinks(Array $css) {
		$this->css = array_merge($this->css, $css);
	}

	/**
	 * add js files for linking in html header
	 *
	 * @param array $css
	 */
	public function appendJsLinks(Array $js) {
		$this->js = array_merge($this->js, $js);
	}

	public function htmlHeader($title = NULL, $css = NULL, $js = NULL, $miscstr = NULL) {
		$caption =  !empty($title) ? $title : $this->setMetaValue('title');

		$charset = isset($this->config->site->default_encoding) ? $this->config->site->default_encoding : 'iso-8859-1';

		$html = "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01//EN\" \"http://www.w3.org/TR/html4/strict.dtd\">
			<html>
			<head>
				<title>$caption</title>
				<meta name='keywords' content='{$this->setMetaValue('keywords')}'>
				<meta name='description' content='{$this->setMetaValue('description')}'>
				<meta name='robots' content='{$this->setMetaValue('robots')}'>

				<meta http-equiv='content-type' content='text/html; charset=$charset'>
				<meta http-equiv='Content-Script-Type' content='text/javascript'>
				<meta http-equiv='Content-Style-Type' content='text/css'>

				<meta name='author' content='{$this->setMetaValue('author')}'>

				<link rel='icon' type='image/x-icon' href='/favicon.ico'>\n";

		$css = array_unique(array_merge($this->css, (array) $css));

		if(
			$this->currentDocument == 'index.php' &&
			!in_array('default.css', $css) &&
			file_exists(ltrim($this->config->paths['css_path']['subdir'], '/').'default.css')
		) {
			array_unshift($css, 'default.css');
		}

		foreach($css as $c) {
			$html .= "<link type='text/css' rel='stylesheet' href='{$this->config->paths['css_path']['subdir']}$c'>\n";
		}

		$ie = array('ie', 'ie 6', 'ie 7', 'ie 8');

		foreach($ie as $i) {
			$file = str_replace(' ', '_', $i);
			if(file_exists(ltrim($this->config->paths['css_path']['subdir'], '/')."$file.css")) {
				$html .= "<!--[if ".strtoupper($i)."]><link type='text/css' rel='stylesheet' href='{$this->config->paths['css_path']['subdir']}$file.css'><![endif]-->\n";
			}
		}

		$html .= $this->addJavaScript(array_unique(array_merge($this->js, (array) $js)));
		$html .= $miscstr;

		if(defined('GA_HEADER_CODE')) {
			$html .= GA_HEADER_CODE;
		}

		$html .= '
			</head>

			<body onload="__fireOnLoad();">
			<script type="text/javascript">
				var __onload = [];

				var __fireOnLoad = function() {
					if(typeof __onload === "function") {
						__onload();
						return;
					}
					if(__onload.length) {
						for(var i = 0; i < __onload.length; i++) {
							if(typeof __onload[i] === "function") {
								__onload[i]();
							}
						}
					}
				}
			</script>';

		SimpleTemplate::parseTemplateLocales($html);
		$this->html .= $html;
		return $html;
	}

	public function htmlFooter() {
		$html = '</body></html>';
		$this->html .= $html;
		return $html;
	}

	/**
	 * activates or deactivates
	 * "active" menu entries (selected entries are clickable)
	 *
	 * @param unknown_type $state
	 */
	public function setForceActiveMenu($state) {
		$this->forceActiveMenu = (boolean) $state;
	}

	/**
	 * Print main menu
	 * @param string id of menu (e.g. "main", "aux")
	 * @param integer level (full menu tree if not set)
	 * @param string decorator name of decorator class, gets extented to MenuDecorator{$decorator}
	 * @param boolean forceActiveMenu allows clickable active menu entries
	 * @param mixed additional parameter passed to Menu::render()
	 *
	 * @return string html
	 */
	public function mainMenu($id = NULL, $level = FALSE, $forceActiveMenu = NULL, $decorator = NULL, $renderArgs = NULL) {

		if(empty($id) && !isset($this->config->menus)) {
			return '';
		}

		if(empty($id)) {
			$id = array_shift(array_keys($this->config->menus));
		}

		if(
			!isset($this->config->menus[$id]) ||
			!count($this->config->menus[$id]->getEntries()) && $this->config->menus[$id]->getType() == 'static'
		) {
			return '';
		}

		// get menu
		$m = $this->config->menus[$id];

		// authenticate complete menu if necessary
		if(!$this->authenticateMenu($m)) {
			return '';
		}

		if(is_null($forceActiveMenu)) {
			$forceActiveMenu = $this->forceActiveMenu;
		}

		// if menu has not been prepared yet, do it now (caching avoids re-parsing for submenus)
		if(!in_array($m, $this->primedMenus)) {

			// clear selected menu entries (which remain in the session)
			$this->clearSelectedMenuEntries($m);

			// walk tree, add dynamic menus along the way until an active entry is reached
			$activeEntry = $this->walkMenuTree($m);

			// propagate active entry up
			if($activeEntry) {
				$this->propagateActiveEntry($activeEntry);
			}

			$this->primedMenus[] = $m;
		}

		$css = "{$id}menu";

		// drill down to required submenu (if only submenu needs to be rendered)
		if($level !== FALSE) {
			$css .= "_level_$level";

			if($level > 0) {
				while($level-- > 0) {
					$e = $m->getSelectedEntry();
					if(!$e || !$e->getSubMenu()) {
						break;
					}
					$m = $e->getSubMenu();
				}
				if($level >= 0) {
					return '';
				}
			}
		}

		// instantiate optional decorator class
		if(!empty($decorator)) {
			$className = "MenuDecorator$decorator";
			$m = new $className($m);
		}

		// output
		$html = sprintf('<div id="%s">%s</div>', $css, $m->render($level === FALSE, $forceActiveMenu, $renderArgs));

		// apply template parsers and filters
		SimpleTemplate::parseTemplateLocales($html);
		$this->html .= $html;
		return $html;
	}

	/**
	 * walk menu $m recursively until Webpage::currentPage match is found
	 * if necessary dynamic menus will be added
	 *
	 * @param Menu $m
	 * @return MenuEntry or FALSE
	 *
	 */
	private function walkMenuTree(Menu $m) {

		// return when matching entry in current menu

		if(($e = $m->getSelectedEntry())) {
			return $e;
		}

		// if current menu is dynamic - generate it and return selected entry

		if($m->getType() == 'dynamic') {

			$method = $m->getMethod();
			if(!$method) {
				$method = 'buildDynamicMenu';
			}

			if(method_exists($this, $method) && ($addedMenu = $this->$method($m))) {
				return $addedMenu->getSelectedEntry();
			}
		}

		// search current menu entries

		foreach($m->getEntries() as $e) {

			$sm = $e->getSubMenu();

			// when matching entry is found, build submenu of this entry then return entry

			if($e->getPage() === $this->currentPage) {

				// generate complete dynamic menus

				if($sm && $sm->getType() == 'dynamic') {

					// use either a specified method for building the menu or page::buildDynamicMenu()

					$method = $sm->getMethod();
					if(!$method) {
						$method = 'buildDynamicMenu';
					}

					if(method_exists($this, $method)) {
						$this->$method($sm);
					}
				}

				// postprocess menu: insert dynamic menu entries, modify entries, etc.

				else if($sm && method_exists($this, 'reworkMenu')) {
					$this->reworkMenu($sm);
				}

				return $e;
			}

			if($sm) {
				if($sm->getType() == 'dynamic') {

					$method = $sm->getMethod();
					if(!$method) {
						$method = 'buildDynamicMenu';
					}

					if(method_exists($this, $method) && ($addedSm = $this->$method($sm))) {
						return $addedSm->getSelectedEntry();
					}
				}
				else {

					// insert dynamic entries, modify entries, etc.

					if(method_exists($this, 'reworkMenu')) {
						$this->reworkMenu($sm);
					}

					if(($found = $this->walkMenuTree($sm))) {
						return $found;
					}
				}
			}
		}
		return FALSE;
	}

	/**
	 * marks menu entry $e as active and propagates this status up through menu tree
	 * @param MenuEntry $e
	 */
	private function propagateActiveEntry(MenuEntry $e) {
		$m = $e->getMenu();
		$m->setSelectedEntry($e);

		$parent = $m->getParentEntry();

		if($parent) {
			$parent->getMenu()->setSelectedEntry($parent);
			$this->propagateActiveEntry($parent);
		}

	}

	/**
	 * walks menu tree and clears all previously selected entries
	 * @param Menu $menu
	 */
	private function clearSelectedMenuEntries(Menu $menu) {

		while(($e = $menu->getSelectedEntry())) {

			// dynamic menus come either with unselected entry or have a selected entry explicitly set

			if($menu->getType() == 'static') {
				$menu->clearSelectedEntry();
			}
			if(!($menu = $e->getSubMenu())) {
				break;
			}
		}
	}

	/**
	 * insert dynamic entries
	 *
	 * @param Menu $m
	 */
	private function insertDynamicEntries(Menu $m) {
		if(count($m->getDynamicEntriesNdx())) {
			if(method_exists($this, 'buildDynamicEntries')) {
				$this->buildDynamicEntries($m);
			}
		}
	}

	private function setMetaValue($name) {
		static $metaData = NULL;

		$name = strtolower($name);

		switch($name) {
			case 'title':
				$sep = ' - ';
				break;

			case 'keywords':
				$sep = ',';
				break;

			case 'author':
			case 'robots':
			case 'description':
				$sep = NULL;
				break;

			default:
				return '';
		}

		$metaValue = array();

		// initial value of configuration
		if(!empty($this->config->site->$name)) {
			$metaValue[] = $this->config->site->$name;
		}

		// add possible protected property
		if(!empty($this->$name)) {
			$metaValue[] = $this->$name;
		}

		// add meta value stored in db
		if(!isset($metaData) && method_exists('vxPHP\\Template\Util\SimpleTemplateUtil', 'getPageMetaData')) {
			$metaData = SimpleTemplateUtil::getPageMetaData($this->currentPage);
			if(!empty($metaData)) {
				$metaData = array_change_key_case($metaData, CASE_LOWER);
			}
		}
		if(!empty($metaData[$name])) {
			$metaValue[] = $metaData[$name];
		}

		if(isset($sep)) {
			return implode($sep, $metaValue);
		}
		return array_pop($metaValue);
	}

	/**
	 * Compress JS files with jsMin into one file
	 *
	 * @param array $js
	 * @return string script tag(s)
	 */
	private function addJavaScript(Array $js) {

		if(empty($js)) {
			return '';
		}

		$path = isset($this->config->paths['js_path']['subdir']) ? ($this->config->paths['js_path']['subdir']) : '/';

		if(!$this->compressJS) {
			$jsStart	= "<script type='text/javascript' src='$path";
			$jsStop		= "'></script>\r\n";
			return $jsStart.implode($jsStop.$jsStart, $js).$jsStop;
		}

		$fn = md5(implode('', $js)).'.js';

		$tmpRelPath = defined('TMP_PATH') ? TMP_PATH : DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR;
		$tmpAbsPath = rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR).$tmpRelPath;

		if(!file_exists($tmpAbsPath.$fn)) {
			$jsIn = '';
			while(($file = array_shift($js))) {
				$jsIn .= @file_get_contents(rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR).$path.$file);
			}

			$jsOut = JSMin::minify($jsIn);
			file_put_contents($tmpAbsPath.$fn, $jsOut);
		}

		$tmpRelPath = str_replace('\\', '/', $tmpRelPath);

		return "<script type='text/javascript' src='{$tmpRelPath}{$fn}'></script>";
	}

	/**
	 * authenticate page
	 * checks whether current user/admin fulfills requirements defined in site.ini.xml
	 *
	 * Webpage::authenticateByTableRowAccess()
	 * Webpage::authenticateByMiscRules()
	 * should be implemented on a per-application level
	 *
	 * @return boolean
	 */
	private function authenticate() {
		if(!empty($this->pageConfigData->auth)) {

			$admin = Admin::getInstance();

			if(!$admin->isAuthenticated()) {
				return FALSE;
			}

			if($this->pageConfigData->auth === UserAbstract::AUTH_OBSERVE_TABLE && $admin->getPrivilegeLevel() >= UserAbstract::AUTH_OBSERVE_TABLE) {
				return $this->authenticateByTableRowAccess();
			}
			if($this->pageConfigData->auth === UserAbstract::AUTH_OBSERVE_ROW && $admin->getPrivilegeLevel() >= UserAbstract::AUTH_OBSERVE_ROW) {
				return $this->authenticateByTableRowAccess();
			}

			if($this->pageConfigData->auth >= $admin->getPrivilegeLevel()) {
				return TRUE;
			}
			return $this->authenticateByMiscRules();
		}

		return TRUE;
	}

	/**
	 * fallback method for authenticating page access on observe_table/observe_row level
	 * positive authentication if auth_parameter contains a table name found in the admins table access setting
	 *
	 * @return isAuthenticated
	 */
	protected function authenticateByTableRowAccess() {

		if(empty($this->pageConfigData->auth_parameters)) {
			return FALSE;
		}

		$tables = preg_split('/\s*,\s*/', trim($this->pageConfigData->auth_parameters));
		$admin = Admin::getInstance();

		$matching = array_intersect($tables, $admin->getTableAccess());
		return !empty($matching);
	}

	protected function authenticateByMiscRules() {
		return FALSE;
	}

	/**
	 * authenticate complete menu
	 * checks whether current user/admin fulfills requirements defined in site.ini.xml
	 *
	 * if a menu needs authentication and admin meets the required authentication level the menu entries are checked
	 * if single entries require a higher authentication level, they are hidden by setting their display-property to "none"
	 *
	 * @param Menu $m
	 * @return boolean
	 *
	 * Webpage::authenticateMenuByTableRowAccess(Menu $m)
	 * Webpage::authenticateMenuByMiscRules(Menu $m)
	 * should be implemented on a per-application level
	 */
	private function authenticateMenu(Menu $m) {

		if(is_null($m->getAuth())) {
			return TRUE;
		}

		$admin = Admin::getInstance();

		if(!$admin->isAuthenticated()) {
			return FALSE;
		}

		// unhide all menu entries

		foreach($m->getEntries() as $e) {
			$e->setAttribute('display', NULL);
		}

		if($m->getAuth() === UserAbstract::AUTH_OBSERVE_TABLE && $admin->getPrivilegeLevel() >= UserAbstract::AUTH_OBSERVE_TABLE) {
			if($this->authenticateMenuByTableRowAccess($m)) {
				foreach($m->getEntries() as $e) {
					if(!$this->authenticateMenuEntry($e)) {
						$e->setAttribute('display', 'none');
					}
				}
				return TRUE;
			}
			else {
				return FALSE;
			}
		}

		if($m->getAuth() === UserAbstract::AUTH_OBSERVE_ROW && $admin->getPrivilegeLevel() >= UserAbstract::AUTH_OBSERVE_ROW) {
			if($this->authenticateMenuByTableRowAccess($m)) {
				foreach($m->getEntries() as $e) {
					if(!$this->authenticateMenuEntry($e)) {
						$e->setAttribute('display', 'none');
					}
				}
				return TRUE;
			}
			else {
				return FALSE;
			}
		}

		if($m->getAuth() >= $admin->getPrivilegeLevel()) {
			return TRUE;
		}

		return $this->authenticateMenuByMiscRules($m);
	}

	/**
	 * fallback method for authenticating menu access on observe_table/observe_row level
	 * positive authentication if auth_parameter contains a table name found in the admins table access setting
	 *
	 * @param Menu $m
	 * @return isAuthenticated
	 */
	protected function authenticateMenuByTableRowAccess(Menu $m) {
		$p = $m->getAuthParameters();

		if(empty($p)) {
			return FALSE;
		}

		$tables = preg_split('/\s*,\s*/', trim($p));
		$admin = Admin::getInstance();

		$matching = array_intersect($tables, $admin->getTableAccess());
		return !empty($matching);
	}

	/**
	 * fallback method for a proprietary authentication method
	 *
	 * @param Menu $m
	 * @return isAuthenticated
	 */
	protected function authenticateMenuByMiscRules(Menu $m) {
		return FALSE;
	}

	/**
	 * fallback method for authenticating single menu entry access on observe_table/observe_row level
	 * positive authentication if auth_parameter contains a table name found in the admins table access setting
	 *
	 * @param MenuEntry $e
	 * @return isAuthenticated
	 */
	protected function authenticateMenuEntry(MenuEntry $e) {
		$p = $e->getAuthParameters();

		if(empty($p)) {
			return FALSE;
		}

		$tables = preg_split('/\s*,\s*/', trim($p));
		$admin = Admin::getInstance();

		$matching = array_intersect($tables, $admin->getTableAccess());
		return !empty($matching);
	}

	/**
	 * validates request parameters
	 * @param array Request
	 * @return void
	 *
	 * possible comparison patterns of $pageRequests are
	 * (string) "isset":	checks for presence of parameter
	 * array:				checks for existence of parameter value in array values
	 * string:				must be a regular expressions against which the parameters are matched
	 *
	 * if a parameter does not validate and $requestDefaults[$parameter] is present,
	 * this default value gets assigned to the parameter
	 *
	 * any request not explicitly mentioned gets automatically validated
	 */
	private function validateRequests($req) {
		foreach ($this->allowedRequests as $k => $v) {

			if(is_string($v) && $v == 'isset') {
				if(isset($req[$k])) {
					$this->validatedRequests[$k] = true;
				}
				else {
					$this->validatedRequests[$k] = false;
				}
			}

			else if(isset($req[$k])) {
				if(is_array($v)) {
					if(in_array($req[$k], $v)) {
						$this->validatedRequests[$k] = $req[$k];
					}
				}

				else if(preg_match($v, $req[$k])) {
					$this->validatedRequests[$k] = $req[$k];
				}
			}

			unset($req[$k]);
			if(isset($this->requestDefaults[$k]) && !isset($this->validatedRequests[$k])) {
				$this->validatedRequests[$k] = $this->requestDefaults[$k];
			}
		}
		$this->validatedRequests = array_merge($this->validatedRequests, $req);
	}

	/**
	 * JS-HTTP-(Ajax)-Requests abarbeiten
	 */
	private function handleXHR() {
		if(($req = isset($this->config->_get['xmlHttpRequest']))) {
			$this->validateRequests(json_decode($this->config->_get['xmlHttpRequest'], true));
		}

		else if(($req = isset($_POST['xmlHttpRequest']))) {
			$this->validateRequests(json_decode($_POST['xmlHttpRequest'], true));
		}

		else if(($req = isset($this->config->_get['ifuRequest']))) {

			$_POST['httpRequest'] = 'ifuSubmit';
			$this->validateRequests($_POST);

			echo json_encode($this->handleHttpRequest());
			exit;

		}

		else {
			return;
		}

		if($req && !empty($this->validatedRequests)) {
			if($this->validatedRequests['httpRequest'] === 'apcPoll') {
				if($this->config->server['apc_on'] && isset($this->validatedRequests['id'])) {
					$response = apc_fetch("upload_{$this->validatedRequests['id']}");
				}
				if(isset($response['done']) && $response['done'] == 1) {
					apc_clear_cache('user');
				}
			}

			else {
				$response = $this->handleHttpRequest();

				if(isset($this->validatedRequests['echo']) && $this->validatedRequests['echo'] == 1) {
					$echo = isset($this->config->_get['xmlHttpRequest'])
						?
						json_decode(stripslashes($this->config->_get['xmlHttpRequest']))
						:
						json_decode(stripslashes($_POST['xmlHttpRequest']));

					unset($echo->echo);
					$response = array('echo' => $echo, 'response' => $response);
				}
			}
		}

		if(!empty($response)) {
			header('Content-Type: text/plain; charset=UTF-8');
			echo json_encode($response);
		}
		exit;
	}

	/**
	 * deal with http requests coming from client JS
	 * @return array $response
	 */
	protected function handleHttpRequest() {
		return 'HTTP-Request received.';
	}

	/**
	 * shortened header call
	 * @param string destination page
	 */
	protected function redirect($destPage = NULL) {
		if(is_null($destPage)) {
			$script 	= $this->config->getDocument();
			$page		= $this->config->pages[$script][$this->currentPage]->defaultRedirect;
			$destPage	= empty($page) ? (defined('DEFAULT_REDIRECT') ? DEFAULT_REDIRECT : $script) : "$script?page=$page";
		}
		$destPage	= "/$destPage";

		if($this->config->site->use_nice_uris && NiceURI::isPlainURI($destPage)) {
			$destPage = NiceURI::toNice($destPage);
		}
		header("Location: http://{$_SERVER['HTTP_HOST']}$destPage", TRUE, 303);
		exit;
	}

	/**
	 * generate error and (optional) error page content
	 *
	 * @param integer $errorCode
	 */
	protected function generateHttpError($errorCode = 404) {
		header("{$_SERVER['SERVER_PROTOCOL']} 404 Not Found");
		echo "<h1>Error $errorCode</h1>";
		exit();
	}
}
?>

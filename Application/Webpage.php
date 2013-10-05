<?php

namespace vxPHP\Application;

use vxPHP\Application\Exception\WebpageException;
use vxPHP\Application\Menu\Menu;
use vxPHP\Application\MenuEntry\MenuEntry;
use vxPHP\Template\SimpleTemplate;
use vxPHP\User\UserAbstract;
use vxPHP\User\Admin;
use vxPHP\Util\JSMin;
use vxPHP\Template\Util\SimpleTemplateUtil;
use vxPHP\Database\Mysqldbi;
use vxPHP\Config\Config;
use vxPHP\Http\Request;
use vxPHP\Http\Router;
use vxPHP\Util\LocalesFactory;
use vxPHP\Http\StatusCode;

/**
 * Parent class for webpages,
 * provides page-independent functionality and fallbacks
 * handles xmlHttpRequests of clients
 *
 * @author Gregor Kofler
 * @version 1.21.0 2013-10-05
 *
 */

abstract class Webpage {

				/**
				 * @var string
				 */

	public		$html,

				/**
				 * @var string
				 * the current script name (e.g. index.php, admin.php, ...)
				 */
				$currentDocument = NULL,

				/**
				 * @var \vxPHP\Http\Route
				 */
				$route,

				/**
				 * @var array
				 * path segments stripped from (beautified) document (e.g. admin/...) and locale
				 */
				$pathSegments = array();

				/**
				 * @var Config
				 */
	protected	$config,

				/**
				 * @var Mysqldbi
				 */
				$db,

				/**
				 * @var \vxPHP\Http\Request
				 */
				$request,

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

	public function __construct() {

		// set up references required in controllers

		$this->config	= Application::getInstance()->getConfig();
		$this->db		= Application::getInstance()->getDb();

		$this->request			= Request::createFromGlobals();
		$this->route			= Router::getRouteFromPathInfo();
		$this->currentDocument	= basename($this->request->getScriptName());
		$this->pathSegments		= explode('/', trim($this->request->getPathInfo(), '/'));

		// skip script name

		if($this->config->site->use_nice_uris && $this->currentDocument != 'index.php') {
			array_shift($this->pathSegments);
		}

		// skip locale if one found

		if(count($this->pathSegments) && in_array($this->pathSegments[0], LocalesFactory::getAllowedLocales())) {
			array_shift($this->pathSegments);
		}

		$this->handleXHR();
	}

	/**
	 * Set value of a single meta element
	 * valid names are: author, description, robots, keywords
	 *
	 * @param string $name
	 * @param string $value
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

	/**
	 * build complete HTML header
	 *
	 * @param string $title
	 * @param string $css
	 * @param string $js
	 * @param string $miscstr
	 * @return string
	 */
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
			if(substr($c, 0, 1) !== '/') {
				$html .= "<link type='text/css' rel='stylesheet' href='{$this->config->paths['css_path']['subdir']}$c'>\n";
			}
			else {
				$html .= "<link type='text/css' rel='stylesheet' href='$c'>\n";
			}
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
			!count($this->config->menus[$id]->getEntries()) &&
			$this->config->menus[$id]->getType() == 'static'
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
			// use route id to identify current page in case path segment is empty (e.g. splash page)

			$this->walkMenuTree($m, $this->pathSegments[0] === '' ? array($this->route->getRouteId()) : $this->pathSegments);

			// cache menu for multiple renderings

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
			$className = "vxPHP\\Webpage\\Menu\\Decorator\\MenuDecorator$decorator";
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
	 * walk menu $m recursively until path segments are no longer matching, or menu tree ends
	 * if necessary dynamic menus will be added
	 *
	 * @param Menu $m
	 * @param array $pathSegments
	 *
	 * @return MenuEntry or void
	 *
	 */
	private function walkMenuTree(Menu $m, array $pathSegments) {

		if(!count($pathSegments)) {
			return;
		}

		// get current page id to evaluate active menu entry

		$idToFind = array_shift($pathSegments);

		// return when matching entry in current menu

		if(($e = $m->getSelectedEntry())) {
			return $e;
		}

		// if current (sub-)menu is dynamic - generate it and return selected entry

		if($m->getType() == 'dynamic') {

			$method = $m->getMethod();
			if(!$method) {
				$method = 'buildDynamicMenu';
			}

			if(method_exists($this, $method) && ($addedMenu = $this->$method($m, $pathSegments))) {
				return $addedMenu->getSelectedEntry();
			}
		}

		foreach($m->getEntries() as $e) {

			// path segment doesn't match menu entry - finish walk

			if($e->getPage() === $idToFind) {

				$e->getMenu()->setSelectedEntry($e);
				$sm = $e->getSubMenu();

				// if submenu is flagged dynamic: try to generate it

				if($sm && $sm->getType() == 'dynamic') {

					// use either a specified method for building the menu or page::buildDynamicMenu()

					$method = $sm->getMethod();

					if(!$method) {
						$method = 'buildDynamicMenu';
					}

					if(method_exists($this, $method)) {
						$this->$method($sm, $pathSegments);
					}
				}

				// otherwise postprocess menu: insert dynamic menu entries, modify entries, etc.

				else if ($sm && method_exists($this, 'reworkMenu')) {
					$this->reworkMenu($sm);
				}

				// walk  into submenu

				if($sm) {
					$this->walkMenuTree($sm, $pathSegments);
				}
			}
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

		if(!isset($metaData)) {

			$pageId = $this->route->getRouteId();

			if($pageId == 'default') {
				$pageId = end($this->pathSegments);
			}

			$metaData = SimpleTemplateUtil::getPageMetaData($pageId);

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
	 * fallback method for authenticating page access on observe_table/observe_row level
	 * positive authentication if auth_parameter contains a table name found in the admins table access setting
	 *
	 * @return isAuthenticated
	 */
	protected function authenticateByTableRowAccess() {

		$authParameters = $this->route->getAuthParameters();

		if(is_null($authParameters)) {
			return FALSE;
		}

		$tables = preg_split('/\s*,\s*/', trim($authParameters));
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

		return !array_intersect($tables, $admin->getTableAccess());

	}

	/**
	 * handle JS-HTTP-(Ajax)-Requests
	 *
	 * look for xmlHttpRequest array and fill
	 * ParameterBags for $_POST and $_GET accordingly
	 */
	private function handleXHR() {

		$parameters = array();

		if($this->request->getMethod() === 'GET' && $this->request->query->get('xmlHttpRequest')) {
			$bag = 'query';
			foreach(json_decode($this->request->query->get('xmlHttpRequest'), TRUE) as $key => $value) {
				$parameters[$key] = $value;
			}
		}

		else if($this->request->getMethod() === 'POST' && $this->request->request->get('xmlHttpRequest')) {
			$bag = 'request';
			foreach(json_decode($this->request->request->get('xmlHttpRequest'), TRUE) as $key => $value) {
				$parameters[$key] = $value;
			}
		}

		// handle Iframe File Upload - works with vxJS.widget.xhrForm

		else if($this->request->query->get('ifuRequest')) {

			$this->request->request->set('httpRequest', 'ifuSubmit');
			echo json_encode($this->handleHttpRequest());
			exit();

		}

		else {
			return;
		}

		if(count($parameters)) {

			$this->request->$bag->add($parameters);

			// handle request for apc upload poll

			if($this->request->$bag->get('httpRequest') === 'apcPoll') {

				$id = $this->request->$bag->get('id');
				if($this->config->server['apc_on'] && $id) {
					$response = apc_fetch('upload_' . $id);
				}
				if(isset($response['done']) && $response['done'] == 1) {
					apc_clear_cache('user');
				}

			}

			else {

				$response = $this->handleHttpRequest();

				if($this->request->$bag->get('echo') == 1) {
					$echo = json_decode(stripslashes($this->request->$bag->get('xmlHttpRequest')));
					unset($echo->echo);
					$response = array('echo' => $echo, 'response' => $response);
				}
			}
		}

		if(!empty($response)) {
			header('Content-Type: text/plain; charset=UTF-8');
			echo json_encode($response);
		}
		exit();
	}

	/**
	 * deal with http requests coming from client JS
	 * @return array $response
	 */
	protected function handleHttpRequest() {
		return 'HTTP-Request received.';
	}

	/**
	 * prepares and executes a Route::redirect
	 *
	 * @param string destination page id
	 * @param string $document
	 * @param array query
	 * @param int $statusCode
	 *
	 */
	protected function redirect($path = NULL, $document = NULL, $queryParams = array(), $statusCode = 303) {

		if(is_null($path)) {
			$this->route->redirect($queryParams, $statusCode);
		}

		if(is_null($document)) {
			$document = $this->currentDocument;
		}

		$urlSegments = array(
			$this->request->getSchemeAndHttpHost()
		);

		if(Application::getInstance()->getConfig()->site->use_nice_uris == 1) {
			if($document !== 'index.php') {
				$urlSegments[] = basename($document, '.php');
			}
		}
		else {
			$urlSegments[] = $document;
		}

		$urlSegments[] = trim($path, '/');

		if($queryParams) {
			$query = '?' . http_build_query($queryParams);
		}

		else {
			$query = '';
		}

		header(
			'Location: ' .
			implode('/', $urlSegments) .
			$query,
			TRUE,
			$statusCode
		);

		exit();

	}

	/**
	 * generate error and (optional) error page content
	 *
	 * @param integer $errorCode
	 */
	public static function generateHttpError($errorCode = 404) {
		header("{$_SERVER['SERVER_PROTOCOL']} $errorCode " . StatusCode::$code[$errorCode]);
		echo '<h1>' . $errorCode . ' ' . StatusCode::$code[$errorCode] . '</h1>';
		exit();
	}
}
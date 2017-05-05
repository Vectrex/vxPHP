<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Template\Filter;

use vxPHP\Webpage\Menu\Menu;
use vxPHP\Http\Request;
use vxPHP\Application\Application;

/**
 * Simple filter that replaces href attribute values beginning with $ into route ids
 * both simple route ids and paths with route ids are parsed
 *
 * @author Gregor Kofler
 */
class AnchorHref extends SimpleTemplateFilter implements SimpleTemplateFilterInterface {

	/**
	 * (non-PHPdoc)
	 *
	 * @see \vxPHP\SimpleTemplate\Filter\SimpleTemplateFilterInterface::parse()
	 *
	 */
	public function apply(&$templateString) {

		$templateString = preg_replace_callback(
			'~<a(.*?)\s+href=("|\')\$([a-z0-9_]+[a-z0-9_.\/-]*)(.*?)\2(.*?)>~i',
			array($this, 'filterHrefWithPath'),
			$templateString
		);

		$templateString = preg_replace_callback(
			'~<a(.*?)\s+href=("|\')\$\/([a-z0-9_.-]+)(.*?)\2(.*?)>~i',
			array($this, 'filterHref'),
			$templateString
		);

	}

	/**
	 * callback to turn href shortcuts into site conform valid URLs
	 * tries to build a path reflecting the position of the page in a nested menu
	 *
	 * $foo/bar?baz=1 becomes /level1/level2/foo/bar?baz=1 or index.php/level1/level2/foo/bar?baz=1
	 *
	 * @param array $matches
	 * @return string
	 */
	private function filterHrefWithPath($matches) {

		static $script;
		static $niceUri;
		static $config;
		static $assetsPath;

		if(is_null($config)) {
			$application = Application::getInstance();

			$config		= $application->getConfig();
			$niceUri	= $application->hasNiceUris();
		}

		if(is_null($script)) {
			$script = basename(trim(Request::createFromGlobals()->getScriptName(), '/'));
		}

		$matchSegments = explode('/', $matches[3]);
		$pathToFind = array_shift($matchSegments);
		
		$recursiveFind = function(Menu $m) use (&$recursiveFind, $pathToFind) {

			foreach($m->getEntries() as $e) {

				if($e->getPath() === $pathToFind) {
					return $e;
				}

				if(($sm = $e->getSubMenu()) && $sm->getType() !== 'dynamic') {
					if($e = $recursiveFind($sm)) {
						return $e;
					}
				}
			}

		};

		foreach($config->menus as $menu) {
			if($menu->getScript() === $script) {
				if(($e = $recursiveFind($menu))) {
					break;
				}
			}
		}

		if(isset($e)) {

			$pathSegments = [$e->getPath()];

			while($e = $e->getMenu()->getParentEntry()) {
				$pathSegments[] = $e->getPath();
			}

			$uriParts = [];

			if($niceUri) {
				if($script !== 'index.php') {
					$uriParts[] = basename($script, '.php');
				}
			}
			else {
				if(is_null($assetsPath)) {
					$assetsPath = Application::getInstance()->getRelativeAssetsPath();
				}

				$uriParts[] = ltrim($assetsPath, '/') . $script;
			}
			if(count($pathSegments)) {
				$uriParts[] = implode('/', array_reverse($pathSegments));
			}
			if(count($matchSegments)) {
				$uriParts[] = implode('/', $matchSegments);
			}

			$uri = implode('/', $uriParts) . $matches[4];

			return "<a{$matches[1]} href={$matches[2]}/$uri{$matches[2]}{$matches[5]}>";

		}
	}

	/**
	 * callback to turn href shortcuts into site conform valid URLs
	 *
	 * $/foo/bar?baz=1 becomes /foo/bar?baz=1 or index.php/foo/bar?baz=1
	 *
	 * @param array $matches
	 * @return string
	 */
	private function filterHref($matches) {

		static $script;
		static $niceUri;

		if(empty($script)) {
			$script = trim(Request::createFromGlobals()->getScriptName(), '/');
		}

		if(empty($niceUri)) {
			$niceUri = Application::getInstance()->getConfig()->site->use_nice_uris == 1;
		}

		$matches[4] = html_entity_decode($matches[4]);

		$uriParts = [];

		if($niceUri) {
			if($script !== 'index.php') {
				$uriParts[] = basename($script, '.php');
			}
		}
		else {
			$uriParts[] = $script;
		}

		if($matches[3] !== '') {
			$uriParts[] = $matches[3];
		}

		$uri = implode('/', $uriParts) . $matches[4];

		return "<a{$matches[1]} href={$matches[2]}/$uri{$matches[2]}{$matches[5]}>";
	}
}

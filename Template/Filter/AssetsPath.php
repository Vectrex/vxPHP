<?php

namespace vxPHP\Template\Filter;

use vxPHP\Application\Application;

/**
 * when use_nice_uris is enabled and an asset path is defined in the configuration,
 * this filter replaces in href and src attribute values a leading asset path (if defined in the configuration)
 * src attributes of images beginning with a leading $ are always extended with "/[asset_path]/img/site/" to the left
 *
 * @author Gregor Kofler
 */
class AssetsPath extends SimpleTemplateFilter implements SimpleTemplateFilterInterface {

	/**
	 * (non-PHPdoc)
	 *
	 * @see \vxPHP\SimpleTemplate\Filter\SimpleTemplateFilterInterface::parse()
	 *
	 */
	public function apply(&$templateString) {

		if(!$assetPath = Application::getInstance()->getRelativeAssetsPath()) {
			$assetPath = '/';
		}

		// extend <img src="$..." ...> with path to site images

		$templateString = preg_replace_callback(
			'~<img(.*?)\s+src=("|\')\$([^"\']+)\2(.*?)>~i',
			function($matches) use ($assetPath) {
				return '<img' . $matches[1] . ' src='. $matches[2] . $assetPath . 'img/site/' . $matches[3] . $matches[2] . $matches[4] . '>';
			},
			$templateString
		);

		// change path of src attributes when asset_path is set and use_nice_uris is not set in the configuration
		// only relative links (without protocol) are matched

		$templateString = preg_replace_callback(
			'~<(.*?)\s+src=("|\')(?![a-z]+://).*?([^"\']+)\2(.*?)>~i',
			function($matches) use ($assetPath) {
				return '<' . $matches[1] . ' src='. $matches[2] . $assetPath . rtrim($matches[3], '/') . $matches[2] . $matches[4] . '>';
			},
			$templateString
		);

		//@todo
	}

}

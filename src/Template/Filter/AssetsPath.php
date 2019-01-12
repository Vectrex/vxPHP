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

		$application = Application::getInstance();

		// change path of src and href attributes when asset_path is set and use_nice_uris is not set in the configuration
		// only relative links (without protocol) are matched
		// when nice uris are used URL rewriting does the job, when no assets path is set, everything is in place already

		if($application->getRelativeAssetsPath()) {

			// src attributes

            $assetsPath = '/' . $application->getRelativeAssetsPath();

			$templateString = preg_replace_callback(
				'~<(.*?)\s+src=("|\')(?![a-z]+://)([^"\']+)\2(.*?)>~i',
				function($matches) use ($assetsPath) {
					return '<' . $matches[1] . ' src='. $matches[2] . $assetsPath . ltrim($matches[3], '/') . $matches[2] . $matches[4] . '>';
				},
				$templateString
			);

			// href attributes

			$templateString = preg_replace_callback(
				'~<(.*?)\s+href=("|\')(?![a-z]+://)([^"\']+)\2(.*?)>~i',
				function($matches) use ($assetsPath) {

					// check whether this URL has already the assets path prefixed and contains a script - in this case don't change the URL

					if(preg_match('~^' . $assetsPath . '\w+\.php~', $matches[3])) {
						return $matches[0];
					}

					return '<' . $matches[1] . ' href='. $matches[2] . $assetsPath . ltrim($matches[3], '/') . $matches[2] . $matches[4] . '>';
				},
				$templateString
			);

		}
	}

}

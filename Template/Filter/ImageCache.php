<?php

namespace vxPHP\Template\Filter;

use vxPHP\File\FilesystemFolder;
use vxPHP\Template\Exception\SimpleTemplateException;
use vxPHP\Image\ImageModifier;
use vxPHP\Application\Application;

/**
 * This filter replaces images which are set to specific sizes by optimized resized images in caches
 * in addition cropping and turning into B/W can be added to the src attribute of the image
 *
 * @author Gregor Kofler
 *
 */
class ImageCache extends SimpleTemplateFilter implements SimpleTemplateFilterInterface {

	/**
	 * @var array
	 *
	 * markup possibilities to which the filter will be applied
	 */
	private	$markupToMatch = array(
				'~<img(.*?)\s+src=("|\')(.*?)#([\w\s\.\|]+)\2(.*?)>~i',
				'~<img.*?\s+(width|height|src)=("|\')(.*?)\2.*?\s+(width|height|src)=("|\')(.*?)\5.*?\s+(width|height|src)=("|\')(.*?)\8.*?>~i',
				'~<img.*?\s+(style|src)=("|\')(.*?)\2.*?\s+(style|src)=("|\')(.*?)\5.*?>~i',
				'~url\s*\(("|\'|)(.*?)#([\w\s\.\|]+)\1\)~i'
			);

	/**
	 * (non-PHPdoc)
	 *
	 * @see \vxPHP\SimpleTemplate\Filter\SimpleTemplateFilterInterface::parse()
	 *
	 */
	public function apply(&$templateString) {

		$templateString = preg_replace_callback(
			$this->markupToMatch,
			array($this, 'filterCallBack'),
			$templateString
		);

	}

	/**
	 * replaces the matched string
	 *
	 * @param array $matches
	 * @throws SimpleTemplateException
	 * @return string
	 */
	private function filterCallBack($matches) {

		// <img src="..." style="width: ...; height: ...">

		if(count($matches) === 7) {

			// $matches[1, 4] - src|style
			// $matches[3, 6] - path|rules

			if($matches[2] == 'src') {
				$src	= $matches[3];
				$style	= $matches[6];
			}

			else {
				$src	= $matches[6];
				$style	= $matches[3];
			}

			// analyze dimensions

			if(!preg_match('~(width|height):\s*(\d+)px;.*?(width|height):\s*(\d+)px~', $style, $dimensions)) {
				return $matches[0];
			}

			if($dimensions[1] == 'width') {
				$width	= $dimensions[2];
				$height = $dimensions[4];
			}
			else {
				$width	= $dimensions[4];
				$height = $dimensions[2];
			}

			$pi			= pathinfo($src);
			$actions	= "resize $width $height";
		}

		// <img src="..." width="..." height="...">

		else if(count($matches) === 10) {

			// $matches[1, 4, 7] - src|width|height
			// $matches[3, 6, 9] - path|dimension 1|dimension 2

			foreach($matches as $ndx => $m) {

				$m = strtolower($m);

				if($m === 'src') {
					$src = $matches[$ndx + 2];
				}
				else if($m === 'height') {
					$height = (int) $matches[$ndx + 2];
				}
				else if($m === 'width') {
					$width = (int) $matches[$ndx + 2];
				}

			}

			$pi			= pathinfo($src);
			$actions	= "resize $width $height";
		}

		// url(...#...)

		else if (count($matches) == 4) {

			// $matches[2] - File
			// $matches[3] - Modifiers

			$src		= $matches[2];
			$pi			= pathinfo($src);
			$actions	= $matches[3];
		}

		// <img src="...#...">

		else {
			// $matches[3] - File
			// $matches[4] - Modifiers

			$src		= $matches[3];
			$pi			= pathinfo($src);
			$actions	= $matches[4];
		}

		$pi['extension'] = isset($pi['extension']) ? ".{$pi['extension']}" : '';

		$dest =
			$pi['dirname'] . DIRECTORY_SEPARATOR .
			FilesystemFolder::CACHE_PATH . DIRECTORY_SEPARATOR .
			$pi['filename'] .
			$pi['extension'] .
			'@' .
			$actions .
			$pi['extension'];

		$path = Application::getInstance()->getAbsoluteAssetsPath() . ltrim($dest, DIRECTORY_SEPARATOR);

		if(!file_exists($path)) {

			$cachePath =
				Application::getInstance()->getAbsoluteAssetsPath() .
				ltrim($pi['dirname'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR .
				FilesystemFolder::CACHE_PATH;

			if(!file_exists($cachePath)) {
				if(!@mkdir($cachePath)) {
					throw new SimpleTemplateException("Failed to create cache folder $cachePath");
				}
				chmod($cachePath, 0777);
			}

			// create cachefile

			$actions	= explode('|', $actions);
			$imgEdit	= new ImageModifier(
				Application::getInstance()->getAbsoluteAssetsPath() .
				$pi['dirname'] . DIRECTORY_SEPARATOR .
				$pi['basename']
			);

			foreach($actions as $a) {
				$params = preg_split('~\s+~', $a);

				$method = array_shift($params);

				if(method_exists('vxPHP\\Image\\ImageModifier', $method)) {
					call_user_func_array(array($imgEdit, $method), $params);
				}
			}

			$imgEdit->export($path);
		}

		// @TODO this _assumes_ that $matches[3] occurs only once

		if(count($matches) === 10 || count($matches) === 7) {
			return str_replace($src, $dest, $matches[0]);
		}
		if(count($matches) === 6) {
			return "<img{$matches[1]} src={$matches[2]}$dest{$matches[2]}{$matches[5]}>";
		}
		else {
			return "url({$matches[1]}$dest{$matches[1]})";
		}

	}
}

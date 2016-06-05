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

use vxPHP\File\FilesystemFolder;
use vxPHP\Template\Exception\SimpleTemplateException;
use vxPHP\Application\Application;
use vxPHP\Image\ImageModifierFactory;

/**
 * This filter replaces images which are set to specific sizes by optimized resized images in caches
 * in addition cropping and turning into B/W can be added to the src attribute of the image
 * 
 * @version 1.2.1 2016-06-05
 * @author Gregor Kofler
 * 
 * @todo parse inline url() style rule
 */
class ImageCache extends SimpleTemplateFilter implements SimpleTemplateFilterInterface {

	/**
	 * @var array
	 *
	 * markup possibilities to which the filter will be applied
	 */
	private	$markupToMatch = array(
		'~<img\s+.*?src=(["\'])(.*?)\1.*?>~i'
	);

	/**
	 * (non-PHPdoc)
	 *
	 * @see vxPHP\SimpleTemplate\Filter\SimpleTemplateFilterInterface::parse()
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
	 * uses regular expression for a faster processing where useful
	 * $matches[0] contains complete image tag
	 * $matches[2] the src attribute value
	 *
	 * @param array $matches
	 * @throws SimpleTemplateException
	 * @return string
	 */
	private function filterCallBack($matches) {

		// narrow down the type of replacement, matches[2] contains src attribute value

		// <img src="...#{actions}">

		if(preg_match('~(.*?)#([\w\s\.\|]+)~', $matches[2], $details)) {

			$dest = $this->getCachedImagePath($details[1], $details[2]);
			
			return preg_replace('~src=([\'"]).*?\1~i', 'src="' . $dest . '"', $matches[0]);

		}
		
		// <img src="..." style="width: ...; height: ...">

		else if(preg_match('~\s+style=(["\'])(.*?)\1~i', $matches[0], $details)) {

			// analyze dimensions

			if(!preg_match('~(width|height):\s*(\d+)px;.*?(width|height):\s*(\d+)px~', strtolower($details[2]), $dimensions)) {

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

			$actions	= 'resize ' . $width . ' ' . $height;
			$dest		= $this->getCachedImagePath($matches[2], $actions);

			return preg_replace('~src=([\'"]).*?\1~i', 'src="' . $dest . '"', $matches[0]);

		}

		// <img src="..." width="..." height="...">

		else if(preg_match('~\s+(width|height)=~', $matches[0])) {

			$dom = new \DOMDocument();
			$dom->loadHTML($matches[0]);
			$img = $dom->getElementsByTagName('img')->item(0);

			// if width attribute is not set, this will evaluate to 0 and force a proportional scaling

			$width	= (int) $img->getAttribute('width');
			$height	= (int) $img->getAttribute('height');

			$actions	= 'resize ' . $width . ' ' . $height;
			$dest		= $this->getCachedImagePath($matches[2], $actions);
				
			$img->setAttribute('src', $dest);
			return $dom->saveHTML($img);

		}
		
		else {
			return $matches[0];
		}
		
/*
			// url(...#...), won't be matched by assetsPath filter
			// @FIXME: getRelativeAssetsPath() doesn't observe mod rewrite

			$relAssetsPath = ltrim(Application::getInstance()->getRelativeAssetsPath(), '/');
			return 'url(' . $matches[1] . '/' . $relAssetsPath . $dest . $matches[1] . ')';
		}
*/
	}
	
	/**
	 * retrieve cached image which matches src attribute $src and actions $actions
	 * if no cached image is found, a cached image with $actions applied is created 
	 * 
	 * @param string $src
	 * @param string $actions
	 * @throws SimpleTemplateException
	 * @return string
	 */
	private function getCachedImagePath($src, $actions) {
		
		$pathinfo	= pathinfo($src);
		$extension	= isset($pathinfo['extension']) ? ('.' . $pathinfo['extension']) : '';

		// destination file name

		$dest =
			$pathinfo['dirname'] . '/' .
			FilesystemFolder::CACHE_PATH . '/' .
			$pathinfo['filename'] .
			$extension .
			'@' . $actions .
			$extension;

		// absolute path to cached file

		$path = Application::getInstance()->extendToAbsoluteAssetsPath(ltrim($dest, '/'));

		// generate cache directory and file if necessary

		if(!file_exists($path)) {

			$cachePath = dirname($path);

			if(!file_exists($cachePath)) {

				if(!@mkdir($cachePath)) {
					throw new SimpleTemplateException("Failed to create cache folder $cachePath");
				}
				chmod($cachePath, 0777);
			}

			// apply actions and create file
			
			$actions	= explode('|', $actions);
			$imgEdit	= ImageModifierFactory::create(Application::getInstance()->extendToAbsoluteAssetsPath(ltrim($pathinfo['dirname'], '/') . '/' . $pathinfo['basename']));
		
			foreach($actions as $a) {
				$params = preg_split('~\s+~', $a);
			
				$method = array_shift($params);
			
				if(method_exists($imgEdit, $method)) {
					call_user_func_array(array($imgEdit, $method), $params);
				}
			}
			
			$imgEdit->export($path);
	
		}
		
		return $dest;
	}
}

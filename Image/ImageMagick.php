<?php

namespace vxPHP\Image;

use vxPHP\Image\ImageModifier;
use vxPHP\Image\Exception\ImageModifierException;

/**
 * implements ImageModfier for Imagick
 *
 * @author Gregor Kofler
 * @version 0.1.0 2014-04-02
 */
class ImageMagick extends ImageModifier {

	private $src;
	
	public function __construct($file) {
		
		$src = new \stdClass();
		
		if(!file_exists($file)) {
			throw new ImageModifierException("File $file doesn't exist.");
		}
		
		try {
			$src->ressource = new \Imagick($file);
			$info = $src->ressource->identifyimage();
		}
		catch(\ImagickException $e) {
			throw new ImageModifierException("Imagick reports error for file $file.");
		}

		$this->file		= $file;
		$this->mimeType	= $info['format'];

		$src->width		= $info['geometry']['width'];
		$src->height	= $info['geometry']['height'];

		if(!preg_match('#^image/(?:'.implode('|', $this->supportedFormats).')$#', $this->mimeType)) {
			throw new ImageModifierException("File $file is not of type ".implode(', ', $this->supportedFormats).".");
		}
		
		$this->src = $src;
	}
	
	public function __destruct() {
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Image\ImageModifier::export()
	 */
	public function export($path = NULL, $mimetype = NULL) {
	}
	
	private function do_crop() {

		$args = func_get_args();

		$src = array_shift($args);

		$srcAspectRatio = $src->width / $src->height;

		// single float value given, represents aspect ratio of cropped image

		if(count($args) == 1) {
			if(!is_numeric($args[0]) || $args[0] <= 0) {
				throw new ImageModifierException('Invalid dimension(s) for do_crop(): ' . $args[0]);
			}

			if($srcAspectRatio <= $args[0]) {

				// width determines
				$left = $right = 0;

				// choose upper portion
				$top	= round(($src->height - $src->width / $args[0]) / 3);
				$bottom	= round(($src->height - $src->width / $args[0]) * 2 / 3);
			}
			else {

				// height determines
				$top	= $bottom	= 0;
				$left	= $right	= round(($src->width - $src->height * $args[0]) / 2);
			}
		}

		// width and height given

		else if(count($args) == 2) {

			$width	= (int) $args[0];
			$height	= (int) $args[1];

			if($width > 0 && $height > 0) {
				$left = $right = round(($src->width - $width) / 2);

				if($srcAspectRatio >= 1) {
					// landscape
					$top = $bottom = round(($src->height - $height) / 2);
				}
				else {
					// portrait
					$top	= round(($src->height - $height) / 3);
					$bottom	= round(($src->height - $height) * 2 / 3);
				}
			}

			else {
				throw new ImageModifierException('Invalid dimension(s) for do_crop(): ' . $width . ', ' . $height);
			}
		}

		// top, left, bottom, right

		else if(count($args) == 4) {
			$top	= (int) $args[0];
			$right	= (int) $args[1];
			$bottom	= (int) $args[2];
			$left	= (int) $args[3];
		}
		else {
			throw new ImageModifierException('Insufficient arguments for do_crop()');
		}

		if(!$top && !$bottom && !$left && !$right) {
			throw new ImageModifierException('Invalid boundaries for do_crop()');
		}

		$src->resource->cropImage($right - $left, $bottom - $top, $left, $top);
		return $src;
	}

		
		
	private function do_resize() {
	}
	
	private function do_watermark() {
	}

	private function do_greyscale() {
	}
}

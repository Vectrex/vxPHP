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

	/**
	 * @var \stdClass
	 */
	private $src;
	
	/**
	 * 
	 * @param unknown $file
	 * @throws ImageModifierException
	 */
	public function __construct($file) {
		
		if(!file_exists($file)) {
			throw new ImageModifierException("File $file doesn't exist.");
		}
		
		try {
			$img = new \Imagick($file);
			$info = $img->identifyimage();
		}
		catch(\ImagickException $e) {
			throw new ImageModifierException("Imagick reports error for file $file.");
		}

		$this->file			= $file;
		$this->mimeType		= strtolower(array_shift(explode(' ', $info['format'])));
		$this->srcWidth		= $info['geometry']['width'];
		$this->srcHeight	= $info['geometry']['height'];

		if(!in_array($this->mimeType, $this->supportedFormats)) {
			throw new ImageModifierException("File $file is not of type ".implode(', ', $this->supportedFormats).".");
		}
		
		$src			= new \stdClass();
		$src->resource	= $img;
		$this->src		= $src;
		$this->queue	= array();

	}
	
	public function __destruct() {
		if(isset($this->src)) {
			$this->src->resource->clear();
		}
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Image\ImageModifier::export()
	 */
	public function export($path = NULL, $mimetype = NULL) {
		
		foreach($this->queue as $step) {
			call_user_func_array(array($this, 'do_' . $step->method), array_merge(array($this->src), $step->parameters));
		}

		// @todo
		
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Image\ImageModifier::do_crop()
	 */
	protected function do_crop($src, $top, $left, $bottom, $right) {

		$src->resource->cropImage($right - $left, $bottom - $top, $left, $top);
		return $src;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Image\ImageModifier::do_resize()
	 */
	protected function do_resize($src, $width, $height) {

		$src->resource->resizeImage($width, $height, \Imagick::FILTER_CATROM, 1, FALSE);
		return $src;
		
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Image\ImageModifier::do_watermark()
	 */
	protected function do_watermark($src, $watermarkFile) {
		return $src;
	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Image\ImageModifier::do_greyscale()
	 */
	protected function do_greyscale($src) {
		return $src;
	}
}

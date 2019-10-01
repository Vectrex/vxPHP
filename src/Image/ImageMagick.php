<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Image;

use vxPHP\Image\ImageModifier;
use vxPHP\Image\Exception\ImageModifierException;

/**
 * implements ImageModfier for Imagick
 *
 * @author Gregor Kofler
 * @version 0.3.2 2019-10-01
 * 
 * @todo improve grayscale conversion
 * 
 */
class ImageMagick extends ImageModifier
{
	/**
	 * @var \stdClass
	 */
	private $src;
	
	/**
	 * 
	 * @param unknown $file
	 * @throws ImageModifierException
	 */
	public function __construct($file)
    {
		if(!file_exists($file)) {
            throw new ImageModifierException(sprintf("File '%s' doesn't exist.", $file), ImageModifierException::FILE_NOT_FOUND);
		}
		
		try {
			$img = new \Imagick($file);
		}
		catch(\ImagickException $e) {
            throw new ImageModifierException(sprintf("Imagick reports error '%s' for file %s.", $e->getMessage(), $file), ImageModifierException::WRONG_FILE_TYPE);
		}

		$this->file = $file;
		$this->mimeType = 'image/' . strtolower($img->getImageFormat());
		$this->srcWidth = $img->getImageWidth();
		$this->srcHeight = $img->getImageHeight();

		if(!preg_match('#^image/(?:'.implode('|', $this->supportedFormats).')$#', $this->mimeType)) {
            throw new ImageModifierException(sprintf("File %s is not of type '%s'.", $file, implode("', '", $this->supportedFormats)), ImageModifierException::WRONG_FILE_TYPE);
		}

		$src = new \stdClass();
		$src->resource = $img;
		$src->width = $this->srcWidth;
		$src->height = $this->srcHeight;

		$this->src = $src;
		$this->queue = [];

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
	public function export($path = null, $mimetype = null)
    {
		
		if(!$mimetype) {
			$mimetype = $this->mimeType;
		}

		if(!preg_match('#^image/(?:'.implode('|', $this->supportedFormats).')$#', $mimetype)) {
            throw new ImageModifierException(sprintf("%s not supported by export.", $mimetype), ImageModifierException::WRONG_FILE_TYPE);
		}
		
		$this->path = $path ?: $this->file;

		// if image was not altered, create only copy
		
		if($this->mimeType === $mimetype && !count($this->queue)) {
			copy($this->file, $this->path);
		}
		
		else {

			foreach($this->queue as $step) {
				call_user_func_array([$this, 'do_' . $step->method], array_merge([$this->src], $step->parameters));
			}

			switch($mimetype) {
		
				case 'image/jpeg':
					$this->src->resource->setFormat('jpeg');
					$this->src->resource->setImageCompression(\Imagick::COMPRESSION_JPEG);
					$this->src->resource->setImageCompressionQuality(90);
					break;

				case 'image/png':
					$this->src->resource->setFormat('png');
					$this->src->resource->setImageCompression(\Imagick::COMPRESSION_UNDEFINED);
					$this->src->resource->setImageCompressionQuality(0);
					break;

				case 'image/gif':
					$this->src->resource->setFormat('gif');
					break;
			}
			
			$this->src->resource->writeImage($path);
		}
		
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Image\ImageModifier::do_crop()
	 */
	protected function do_crop(\stdClass $src, $top, $left, $bottom, $right)
    {

		$src->resource->cropImage($src->width - $right - $left, $src->height - $bottom - $top, $left, $top);

		$src->width = $src->width - $right - $left;
		$src->height = $src->height - $bottom - $top;

		return $src;
	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Image\ImageModifier::do_resize()
	 */
	protected function do_resize(\stdClass $src, $width, $height)
    {
		$src->resource->resizeImage($width, $height, \Imagick::FILTER_CATROM, 1, false);
		$src->resource->convolveImage([-1, -0.8, -1, -0.8, 16, -0.8, -1, -0.8, -1]);
		
		$src->width		= $width;
		$src->height	= $height;

		return $src;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Image\ImageModifier::do_watermark()
	 */
	protected function do_watermark(\stdClass $src, $watermarkFile)
    {
		
		if(!file_exists($watermarkFile)) {
            throw new ImageModifierException(sprintf("Watermark file '%s' not found.", $watermarkFile), ImageModifierException::FILE_NOT_FOUND);
		}
		
		$watermark = new \Imagick($watermarkFile);
		$src->resource->compositeImage($watermark, \Imagick::COMPOSITE_OVER, ($src->width - $watermark->getImageWidth()) / 2, ($src->height - $watermark->getImageHeight()) / 2);
		$src->resource->flattenImages();

		$watermark->clear();

		return $src;
	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Image\ImageModifier::do_greyscale()
	 */
	protected function do_greyscale(\stdClass $src)
    {
		$src->resource->modulateImage(100, 0, 100);

		return $src;
	}
}

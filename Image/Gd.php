<?php

namespace vxPHP\Image;

use vxPHP\Image\Exception\ImageModifierException;

/**
 *
 * wraps some image manipulation functionality
 * for gdlib
 *
 * @author Gregor Kofler
 * @version 0.5.0 2014-04-01
 *
 */
class Gd extends ImageModifier {

	private	$source,
			$destinationBuffer,
			$bufferNdx = 0,
			$imageWasAltered = FALSE;

	/**
	 * initializes object with optional filename
	 *
	 * @param string $file
	 * @throws ImageModifierException
	 */
	public function __construct($file) {

		$src = new \stdClass();

		if(!file_exists($file)) {
			throw new ImageModifierException("File $file doesn't exist.");
		}

		$info = @getimagesize($file);
		if($info === FALSE) {
			throw new ImageModifierException("getimagesize() reports error for file $file.");
		}

		$this->file		= $file;
		$this->mimeType	= $info['mime'];

		if(!preg_match('#^image/(?:'.implode('|', $this->supportedFormats).')$#', $this->mimeType)) {
			throw new ImageModifierException("File $file is not of type ".implode(', ', $this->supportedFormats).".");
		}

		switch($this->mimeType) {
			case 'image/jpeg':
				$src->resource = imagecreatefromjpeg($file);
				break;
			case 'image/png':
				$src->resource = imagecreatefrompng($file);
				break;
			case 'image/gif':
				$src->resource = imagecreatefromgif($file);
				break;
		}
		
		$this->srcWidth		= $info[0];
		$this->srcHeight	= $info[1];

		$src->width			= $info[0];
		$src->height		= $info[1];

		$this->source				= $src;
		$this->queue				= array();
		$this->destinationBuffer	= array(new \stdClass(), new \stdClass());
	}

	/**
	 * cleanup resources upon destruct
	 */
	public function __destruct() {
		if(isset($this->source->resource)) {
			imagedestroy($this->source->resource);
		}
		if(isset($this->destinationBuffer[0]->resource)) {
			imagedestroy($this->destinationBuffer[0]->resource);
		}
		if(isset($this->destinationBuffer[1]->resource)) {
			imagedestroy($this->destinationBuffer[1]->resource);
		}
	}

	/**
	 * flips buffers when several manipulating steps are queued
	 */
	private function flipBuffer() {
		$this->bufferNdx = ++$this->bufferNdx % 2;
	}

	/**
	 * performs crop-"command"
	 * 
	 * @param stdClass $src
	 * @param int $top
	 * @param int $left
	 * @param int $bottom
	 * @param int $right
	 */
	private function do_crop($src, $top, $left, $bottom, $right) {

		$dst = new \stdClass();
		$dst->width		= $src->width - $left - $right;
		$dst->height	= $src->height - $top - $bottom;
		$dst->resource	= imagecreatetruecolor($dst->width, $dst->height);

		if($this->mimeType == 'image/png' || $this->mimeType == 'image/gif') {
			imagealphablending($dst->resource, FALSE);
			imagesavealpha($dst->resource, TRUE);
		}

		imagecopy(
			$dst->resource,
			$src->resource,
			0, 0,
			$left, $top,
			$src->width - $left - $right, $src->height - $top - $bottom
		);

		return $dst;
	}

	/**
	 * performs resize-"command"
	 * 
	 * @param stdClass $src
	 * @param int $width
	 * @param int $height
	 */
	private function do_resize($src, $width, $height) {

		$dst = new \stdClass();
		$dst->resource	= imagecreatetruecolor($width, $height);
		$dst->width		= $width;
		$dst->height	= $height;

		if($this->mimeType == 'image/png' || $this->mimeType == 'image/gif') {
			imagealphablending($dst->resource, FALSE);
			imagesavealpha($dst->resource, TRUE);
		}

		imagecopyresampled(
			$dst->resource,
			$src->resource,
			0, 0, 0, 0,
			$dst->width, $dst->height,
			$src->width, $src->height
		);
		
		// add some sharpening

		imageconvolution($dst->resource, array(
			array(-1, -0.8, -1),
			array(-0.8, 16, -0.8),
			array(-1, -0.8, -1)
		), 16 - 7.2, 0);

		return $dst;
	}

	/**
	 * performs "watermark"-command
	 * 
	 * @param stdClass $src
	 * @param string $watermarkFile
	 */
	private function do_watermark($src, $watermarkFile) {

		$stamp			= imagecreatefrompng($watermarkFile);
		$stampWidth		= imagesx($stamp);
		$stampHeight	= imagesy($stamp);

		$dst = new \stdClass();
		$dst->resource	= imagecreatetruecolor($src->width, $src->height);
		$dst->width		= $src->width;
		$dst->height	= $src->height;

		// stamp is centered onto source image, opacity 50%
		imagecopy($dst->resource, $src->resource, 0, 0, 0, 0, $src->width, $src->height);

		$this->imagecopymerge_alpha(
			$dst->resource,
			$stamp,
			($src->width - $stampWidth) / 2,
			($src->height - $stampHeight) / 2,
			0, 0,
			$stampWidth,
			$stampHeight,
			100
		);

		return $dst;
	}

	/**
	 * performs "bw"-command
	 * 
	 * @param stdClass $src
	 */
	private function do_greyscale($src) {

		$dst = new \stdClass();
		$dst->resource	= imagecreatetruecolor($src->width, $src->height);
		$dst->width		= $src->width;
		$dst->height	= $src->height;

		imagecopy($dst->resource, $src->resource, 0, 0, 0, 0, $src->width, $src->height);
		imagefilter($dst->resource, IMG_FILTER_GRAYSCALE);
		return $dst;
	}

	private function imagecopymerge_alpha($dst, $src, $dstX, $dstY, $srcX, $srcY, $srcW, $srcH, $opacity) {
		$cut = imagecreatetruecolor($srcW, $srcH);
		imagecopy($cut, $dst, 0, 0, $dstX, $dstY, $srcW, $srcH);
		imagecopy($cut, $src, 0, 0, $srcX, $srcY, $srcW, $srcH);
		imagecopymerge($dst, $cut, $dstX, $dstY, 0, 0, $srcW, $srcH, $opacity);
		imagedestroy($cut);
    }

    /**
     * (non-PHPdoc)
     * @see \vxPHP\Image\ImageModifier::export()
     */
	public function export($path = NULL, $mimetype = NULL) {

		$src = $this->source;
		$dst = $this->destinationBuffer[0];

		foreach($this->queue as $step) {
			$this->destinationBuffer[$this->bufferNdx] = call_user_func_array(array($this, "do_{$step->method}"), array_merge(array($src), $step->parameters));

			// action did not alter source
			if(!$this->destinationBuffer[$this->bufferNdx]) {
				continue;
			}
			else {
				$this->imageWasAltered = TRUE;
			}

			$src = $this->destinationBuffer[$this->bufferNdx];
			$this->flipBuffer();
		}

		$this->path = isset($path) ? $path : $this->$file;

		if(!isset($mimetype)) {
			$mimetype = $this->mimeType;
		}

		// if image was not altered, create only copy
		if($this->mimeType == $mimetype && !$this->imageWasAltered) {
			copy($this->file, $this->path);
		}

		else {
			switch($mimetype) {
				case 'image/jpeg':
					imagejpeg($src->resource, $this->path, 90);
					break;
				case 'image/png':
					imagepng($src->resource, $this->path, 5);
					break;
				case 'image/gif':
					imagegif($src->resource, $this->path);
					break;
			}
		}
		
	}
}

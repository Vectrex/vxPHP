<?php
/**
 * 
 * wraps image manipulation functionality
 * currently supports resizing and cropping of images
 * requires gdlib
 * 
 * @TODO configurable imagemagick support
 * 
 * @author Gregor Kofler
 * @version 0.4.5 2012-09-16
 *
 */
class ImageEdit {
	private	$source,
			$destinationBuffer,
			$bufferNdx = 0,
			$queue,
			$imageWasAltered = FALSE,
			$mimeType,
			$supportedFormats = array('jpeg', 'gif', 'png'),
			$file,
			$path;

	/**
	 * initializes object with optional filename
	 *  
	 * @param string $file
	 * @throws Exception
	 */
	public function __construct($file = NULL) {
		$src = new stdClass();

		if(isset($file)) {
			if(!file_exists($file)) {
				throw new Exception("File $file doesn't exist.");
			}

			$info = @getimagesize($file);
			if($info === FALSE) {
				throw new Exception("getimagesize() reports error for file $file.");
			}

			$this->file		= $file;
			$this->mimeType	= $info['mime'];

			if(!preg_match('#^image/(?:'.implode('|', $this->supportedFormats).')$#', $this->mimeType)) {
				throw new Exception("File $file is not of type ".implode(', ', $this->supportedFormats).".");
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
			$src->width		= $info[0];
			$src->height	= $info[1];
		}

		$this->source				= $src;
		$this->queue				= new ArrayObject();
		$this->destinationBuffer	= array(new stdClass(), new stdClass());
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
	 * adds a crop-"command" to queue
	 */
	public function crop() {
		$todo = new stdClass();

		$todo->method		= __FUNCTION__;
		$todo->parameters	= func_get_args();

		$this->queue->append($todo);
	}

	/**
	 * adds a resize-"command" to queue
	 */
	public function resize() {
		$todo = new stdClass();

		$todo->method		= __FUNCTION__;
		$todo->parameters	= func_get_args();

		$this->queue->append($todo);
	}

	/**
	 * adds a watermark-"command" to queue
	 */
	public function watermark() {
		$todo = new stdClass();

		$todo->method		= __FUNCTION__;
		$todo->parameters	= func_get_args();

		$this->queue->append($todo);
	}

	/**
	 * turns image into b/w
	 */
	public function greyscale() {
		$todo = new stdClass();
		
		$todo->method		= __FUNCTION__;
		$todo->parameters	= func_get_args();
		
		$this->queue->append($todo);
	}

	/**
	 * performs crop-"command"
	 */
	private function do_crop() {
		$args = func_get_args();

		$src = array_shift($args);

		$srcAspectRatio = $src->width / $src->height;
		
		// single float value given, represents aspect ratio of cropped image
		if(count($args) == 1) {
			if(!is_numeric($args[0]) || $args[0] <= 0) {
				throw new Exception("Invalid dimension(s) for do_crop(): {$args[0]}");
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
				throw new Exception("Invalid dimension(s) for do_crop(): $width, $height");
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
			throw new Exception('Insufficient arguments for do_crop()');
		}

		if(!$top && !$bottom && !$left && !$right) {
			return false;
		}

		$dst = new stdClass();
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
	 */
	private function do_resize() {
		$args = func_get_args();

		$src = array_shift($args);

		// width and/or height given

		if(count($args) >= 2) {
			
			// max limit for width?

			if(preg_match('/max_([1-9]\d*)/i', $args[0], $matches)) {

				$maxWidth	= $matches[1];
				$height		= (int) $args[1];
				$width		= round($height / $src->height * $src->width);

				if($width > $maxWidth) {
					$width	= $maxWidth;
					$height	= round($width / $src->width * $src->height);
				}
			}

			// max limit for height?

			else if(preg_match('/max_([1-9]\d*)/i', $args[1], $matches)) {

				$maxHeight	= $matches[1];
				$width		= (int) $args[0];
				$height		= round($width / $src->width * $src->height);

				if($height > $maxHeight) {
					$height	= $maxHeight;
					$width = round($height / $src->height * $src->width);
				}
			}

			// no limit

			else {
				$width	= (int) $args[0];
				$height	= (int) $args[1];

				if($width != 0 || $height != 0) {
					if($height == 0) {
						$height = round($width / $src->width * $src->height);
					}
					if($width == 0) {
						$width = round($height / $src->height * $src->width);
					}
				}
				
				else {
					throw new Exception("Invalid dimension(s) for do_resize(): $width, $height");
				}
			}
		}

		// single float value given

		else if(count($args) == 1) {
			if(!is_numeric($args[0]) || $args[0] == 0) {
				throw new Exception("Invalid dimension(s) for do_resize(): {$args[0]}");
			}
			$width	= round($src->width * $args[0]);
			$height	= round($src->height * $args[0]);
		}
		else {
			throw new Exception('Insufficient arguments for do_resize()');
		}

		if($width == $src->width && $height == $src->height) {
			return false;
		}

		$dst = new stdClass();
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

		imageconvolution($dst->resource, array(
			array(-1, -0.8, -1),
			array(-0.8, 16, -0.8),
			array(-1, -0.8, -1)
		), 16 - 7.2, 0);

		return $dst;
	}

	/**
	 * performs "watermark"-command
	 */
	private function do_watermark() {
		$args = func_get_args();

		$src = array_shift($args);

		// argument 1 is the PNG file of the stamp 
		$stamp			= imagecreatefrompng($args[0]);
		$stampWidth		= imagesx($stamp);
		$stampHeight	= imagesy($stamp);
		
		$dst = new stdClass();
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
	 */
	private function do_greyscale() {
		$args = func_get_args();
		
		$src = array_shift($args);

		$dst = new stdClass();
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
	 * exports resulting image
	 * all queued commands are applied to the source image
	 * 
	 * file is then stored to $path, format $mimetype
	 * 
	 * @param string $path, output filename, defaults to source filename
	 * @param string $mimetype, output file mimetype, defaults to source mime type
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
?>

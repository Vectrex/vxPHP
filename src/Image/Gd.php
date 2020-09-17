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

use vxPHP\Image\Exception\ImageModifierException;

/**
 * implements ImageModfier for gdLib
 * 
 * @author Gregor Kofler
 * @version 0.5.6 2020-09-17
 */
class Gd extends ImageModifier
{
    /**
     * @var \stdClass
     */
	private	$src;
    /**
     * @var array
     */
	private $destinationBuffer;
    /**
     * @var int
     */
	private $bufferNdx = 0;

	/**
	 * initializes object with optional filename
	 *
	 * @param string $file
	 * @throws ImageModifierException
	 */
	public function __construct(string $file)
    {
		$src = new \stdClass();

		if(!file_exists($file)) {
			throw new ImageModifierException(sprintf("File '%s' doesn't exist.", $file), ImageModifierException::FILE_NOT_FOUND);
		}

		$info = @getimagesize($file);
		if($info === false) {
			throw new ImageModifierException(sprintf("getimagesize() reports error for file '%s'.", $file), ImageModifierException::WRONG_FILE_TYPE);
		}

		$this->file = $file;
		$this->mimeType = $info['mime'];

		if(!preg_match('#^image/(?:'.implode('|', $this->supportedFormats).')$#', $this->mimeType)) {
			throw new ImageModifierException(sprintf("File %s is not of type '%s'.", $file, implode("', '", $this->supportedFormats)), ImageModifierException::WRONG_FILE_TYPE);
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
		
		$this->srcWidth = $info[0];
		$this->srcHeight = $info[1];

		$src->width = $info[0];
		$src->height = $info[1];

		$this->src = $src;
		$this->queue = [];
		$this->destinationBuffer = [new \stdClass(), new \stdClass()];
	}

	/**
	 * cleanup resources upon destruct
	 */
	public function __destruct()
    {
		if(isset($this->src->resource)) {
			imagedestroy($this->src->resource);
		}
		if(isset($this->destinationBuffer[0]->resource)) {
			imagedestroy($this->destinationBuffer[0]->resource);
		}
		if(isset($this->destinationBuffer[1]->resource)) {
			imagedestroy($this->destinationBuffer[1]->resource);
		}
	}

    /**
     * (non-PHPdoc)
     * @param \stdClass $src
     * @param int $top
     * @param int $left
     * @param int $bottom
     * @param int $right
     * @return \StdClass
     * @see \vxPHP\Image\ImageModifier::do_crop()
     */
	protected function do_crop(\stdClass $src, int $top, int $left, int $bottom, int $right): \StdClass
    {
		$dst = new \stdClass();
		$dst->width = $src->width - $left - $right;
		$dst->height = $src->height - $top - $bottom;
		$dst->resource = imagecreatetruecolor($dst->width, $dst->height);

		if($this->mimeType === 'image/png' || $this->mimeType === 'image/gif') {
			imagealphablending($dst->resource, false);
			imagesavealpha($dst->resource, true);
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
     * (non-PHPdoc)
     * @param \stdClass $src
     * @param int $width
     * @param int $height
     * @return \StdClass
     * @see \vxPHP\Image\ImageModifier::do_resize()
     */
	protected function do_resize(\stdClass $src, int $width, int $height): \StdClass
    {
		$dst = new \stdClass();
		$dst->resource = imagecreatetruecolor($width, $height);
		$dst->width = $width;
		$dst->height = $height;

		if($this->mimeType === 'image/png' || $this->mimeType === 'image/gif') {
			imagealphablending($dst->resource, false);
			imagesavealpha($dst->resource, true);
		}

		imagecopyresampled(
			$dst->resource,
			$src->resource,
			0, 0, 0, 0,
			$dst->width, $dst->height,
			$src->width, $src->height
		);
		
		// add some sharpening

		imageconvolution($dst->resource, [
			[-1, -0.8, -1],
			[-0.8, 16, -0.8],
			[-1, -0.8, -1]
		], 16 - 7.2, 0);

		return $dst;
	}

    /**
     * (non-PHPdoc)
     * @param \stdClass $src
     * @param string $watermarkFile
     * @return \StdClass
     * @throws ImageModifierException
     * @see \vxPHP\Image\ImageModifier::do_watermark()
     */
	protected function do_watermark(\stdClass $src, string $watermarkFile): \StdClass
    {
        if(!file_exists($watermarkFile)) {
            throw new ImageModifierException(sprintf("Watermark file '%s' not found.", $watermarkFile), ImageModifierException::FILE_NOT_FOUND);
        }

    	$stamp = imagecreatefrompng($watermarkFile);
		$stampWidth = imagesx($stamp);
		$stampHeight = imagesy($stamp);

		$dst = new \stdClass();
		$dst->resource = imagecreatetruecolor($src->width, $src->height);
		$dst->width = $src->width;
		$dst->height = $src->height;

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
     * (non-PHPdoc)
     * @param \stdClass $src
     * @return \StdClass
     * @see \vxPHP\Image\ImageModifier::do_greyscale()
     */
	protected function do_greyscale(\stdClass $src): \StdClass
    {
    	$dst = new \stdClass();
		$dst->resource = imagecreatetruecolor($src->width, $src->height);
		$dst->width = $src->width;
		$dst->height = $src->height;

		imagecopy($dst->resource, $src->resource, 0, 0, 0, 0, $src->width, $src->height);
		imagefilter($dst->resource, IMG_FILTER_GRAYSCALE);
		return $dst;
	}

	private function imagecopymerge_alpha($dst, $src, $dstX, $dstY, $srcX, $srcY, $srcW, $srcH, $opacity): void
    {
		$cut = imagecreatetruecolor($srcW, $srcH);
		imagecopy($cut, $dst, 0, 0, $dstX, $dstY, $srcW, $srcH);
		imagecopy($cut, $src, 0, 0, $srcX, $srcY, $srcW, $srcH);
		imagecopymerge($dst, $cut, $dstX, $dstY, 0, 0, $srcW, $srcH, $opacity);
		imagedestroy($cut);
    }

    /**
     * (non-PHPdoc)
     * @param string|null $path
     * @param string|null $mimetype
     * @throws ImageModifierException
     * @see \vxPHP\Image\ImageModifier::export()
     */
	public function export(string $path = null, string $mimetype = null): void
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
			$src = $this->src;
			
			foreach($this->queue as $step) {
				$this->destinationBuffer[$this->bufferNdx] = call_user_func_array([$this, 'do_' . $step->method], array_merge([$src], $step->parameters));
				$src = $this->destinationBuffer[$this->bufferNdx];
				$this->bufferNdx = ++$this->bufferNdx % 2;
			}

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

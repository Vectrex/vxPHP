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

/**
 * simple factory for returning an ImageModifier class
 *
 * @author Gregor Kofler
 * @version 0.1.1 2021-05-29
 */

class ImageModifierFactory
{
	/**
	 * @var array
	 * 
	 * associative array containing possible class => required extension combinations
	 */
	private static array $options = ['Gd' => 'gd', 'ImageMagick' => 'imagick'];

    /**
     * holds the preferred option of image manipulation
     * either Gd or ImageMagick
     *
     * @var string|null
     */
	private static ?string $preferredOption = null;

    /**
     * @param string $path
     * @param string|null $preference
     *
     * @return ImageModifier
     */
	public static function create(string $path, string $preference = null): ImageModifier
    {
		// try to set preferred option to $preference ("available" and extension loaded)

		if(
		    $preference &&
            isset(self::$options[$preference]) &&
            class_exists(__NAMESPACE__ . '\\' . $preference) &&
            extension_loaded(self::$options[$preference]))
		{
            self::$preferredOption = $preference;
        }

		// otherwise, iterate over $options

		if(!self::$preferredOption) {
			foreach(self::$options as $class => $ext) {
				if(class_exists(__NAMESPACE__ . '\\' . $class) && extension_loaded($ext)) {
					self::$preferredOption = $class;
					break;
				}
			}
		}

		// pick previously set $preferredOption

		if(self::$preferredOption) {
			$className = __NAMESPACE__ . '\\' . self::$preferredOption;
			return new $className($path);
		}
		
		throw new \RuntimeException('No graphics library support found.');
	}
}
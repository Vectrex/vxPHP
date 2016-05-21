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
 * @version 0.1.0 2014-04-03
 */

class ImageModifierFactory {
	
	/**
	 * @var array
	 * 
	 * associative array containing possible class => required extension combinations
	 */
	private static $options = array('Gd' => 'gd', 'ImageMagick' => 'imagick');

	private static $preferedOption;
	
	/**
	 * @param string $path
	 * @param string $preference
	 * 
	 * @return vxPHP\Image\ImageModifier
	 */
	public static function create($path, $preference = NULL) {
		
		// try to set prefered option to $preference ("available" and extension loaded) 

		if($preference) {
			if(
				isset(self::$options[$preference]) &&
				class_exists(__NAMESPACE__ . '\\' . $preference) &&
				extension_loaded(self::$options[$preference])
			){
				self::$preferedOption = $preference;
			}
		}

		// otherwise iterate over $options

		if(!self::$preferedOption) {

			foreach(self::$options as $class => $ext) {
				
				if(class_exists(__NAMESPACE__ . '\\' . $class) && extension_loaded($ext)) {
					self::$preferedOption = $class;
					break;
				}
			}
			
		}

		// pick previously set $preferedOption

		if(self::$preferedOption) {

			$className = __NAMESPACE__ . '\\' . self::$preferedOption;
			return new $className($path);

		}
		
		throw new \RuntimeException('No graphics library support found.');

	}
}
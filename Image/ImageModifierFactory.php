<?php

namespace vxPHP\Image;

/**
 * factory stub for returning an ImageModifier class
 *
 * @author Gregor Kofler
 * @version 0.1.0 2014-04-02
 */

class ImageModifierFactory {
	
	public static function create($path, $preference = 'Gd') {
		
		$className = __NAMESPACE__ . '\\' . $preference; 
		return new $className($path);

	}
}
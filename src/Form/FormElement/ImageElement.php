<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Form\FormElement;

use vxPHP\Form\FormElement\InputElement;

/**
 * input element of type "image"
 * 
 * @author Gregor Kofler
 */
class ImageElement extends InputElement {
	
	/**
	 * inialize a <input type="image"> element instance
	 * 
	 * @param string $name
	 * @param string $value
	 * @param string $src
	 */
	public function __construct($name, $value = NULL, $src) {

		parent::__construct($name, $value);
		$this->setAttribute('alt', pathinfo($src, PATHINFO_FILENAME));

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\InputElement::render()
	 */
	public function render($force = FALSE) {

		$this->attributes['type'] = 'image';
		return parent::render($force);

	}
}
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

/**
 * input element of type "submit"
 *
 * @author Gregor Kofler
 */
class SubmitInputElement extends InputElement {
	
	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\InputElement::render()
	 */
	public function render($force = FALSE) {

		if(empty($this->html) || $force) {
			$this->attributes['type'] = 'submit';
			$this->html = parent::render(TRUE);
		}

		return $this->html;

	}
}

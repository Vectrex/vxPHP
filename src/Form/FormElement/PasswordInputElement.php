<?php

namespace vxPHP\Form\FormElement;

use vxPHP\Form\FormElement\InputElement;

/**
 * input element of type "password"
 * 
 * @author Gregor Kofler
 */
class PasswordInputElement extends InputElement {

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\InputElement::render()
	 */
	public function render($force = FALSE) {

		if(empty($this->html) || $force) {
			$this->attributes['type'] = 'password';
			$this->html = parent::render(TRUE);
		}

		return $this->html;

	}
}

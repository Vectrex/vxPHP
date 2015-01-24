<?php

namespace vxPHP\Form\FormElement;

use vxPHP\Form\FormElement\InputElement;

class PasswordInputElement extends InputElement {

	/**
	 * inialize a <input type="password"> element instance
	 * 
	 * @param string $name
	 * @param string $value
	 */
	public function __construct($name, $value = NULL) {

		parent::__construct($name, $value);

	}

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

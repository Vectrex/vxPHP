<?php

namespace vxPHP\Form\FormElement;

use vxPHP\Form\FormElement\InputElement;

class PasswordInputElement extends InputElement {
	public function __construct($name, $value = NULL) {
		parent::__construct($name, $value);
	}

	public function render($force = FALSE) {

		if(empty($this->html) || $force) {
			$this->attributes['type'] = 'password';
			$this->html = parent::render(TRUE);
		}

		return $this->html;
	}
}

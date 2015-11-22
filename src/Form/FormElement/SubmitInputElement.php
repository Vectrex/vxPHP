<?php

namespace vxPHP\Form\FormElement;

use vxPHP\Form\FormElement\InputElement;

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

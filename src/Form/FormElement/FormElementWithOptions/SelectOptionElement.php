<?php

namespace vxPHP\Form\FormElement\FormElementWithOptions;

use vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragment;

class SelectOptionElement extends FormElementFragment {

	public function __construct($value, $label, SelectElement $formElement = NULL) {
		parent::__construct($value, NULL, $label, $formElement);
	}

	public function render($force = FALSE) {
		if(empty($this->html) || $force) {
			$selected = $this->selected ? " selected='selected'" : '';
			$this->html = "<option value='{$this->getValue()}'$selected>{$this->getLabel()}</option>";
		}

		return $this->html;
	}
}

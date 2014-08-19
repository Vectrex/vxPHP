<?php

namespace vxPHP\Form\FormElement\FormElementWithOptions;

use vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragment;

class RadioOptionElement extends FormElementFragment {

	public function __construct($value, $label, RadioElement $formElement = NULL) {
		parent::__construct($value, NULL, $label, $formElement);
	}

	public function render($force = FALSE) {
		if(empty($this->html) || $force) {
			$checked = $this->selected ? " checked='checked'" : '';
			$this->html = "<input name='{$this->parentElement->getName()}' type='radio' value='{$this->getValue()}'$checked>{$this->getLabel()}";
		}

		return $this->html;
	}
}

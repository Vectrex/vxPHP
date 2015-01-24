<?php

namespace vxPHP\Form\FormElement\FormElementWithOptions;

use vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragment;

/**
 * a single option belonging to a group of <input type="option"> elements
 * sharing the same name
 *
 * @author Gregor Kofler
 * @version 0.4.0 2015-01-24
 */
class RadioOptionElement extends FormElementFragment {

	/**
	 * initialize option with value, label and parent RadioElement
	 * 
	 * @param string $value
	 * @param string $label
	 * @param RadioElement $formElement
	 */
	public function __construct($value, $label, RadioElement $formElement = NULL) {

		parent::__construct($value, NULL, $label, $formElement);

	}

	/**
	 * render element; when $force is FALSE a cached element rendering is re-used 
	 * 
	 * @param string $force
	 */
	public function render($force = FALSE) {

		if(empty($this->html) || $force) {
			$checked = $this->selected ? " checked='checked'" : '';
			$this->html = "<input name='{$this->parentElement->getName()}' type='radio' value='{$this->getValue()}'$checked>{$this->getLabel()}";
		}

		return $this->html;

	}
}

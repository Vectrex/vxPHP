<?php

namespace vxPHP\Form\FormElement\FormElementWithOptions;

use vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragment;

/**
 * a single option of a select element
 * 
 * @author Gregor Kofler
 * @version 0.4.0 2015-01-24
 */
class SelectOptionElement extends FormElementFragment {

	/**
	 * initialize value and label and establish reference to SelectElement
	 * 
	 * @param string $value
	 * @param string $label
	 * @param SelectElement $formElement
	 */
	public function __construct($value, $label, SelectElement $formElement = NULL) {

		parent::__construct($value, NULL, $label, $formElement);

	}

	/**
	 * render option element; when $force is FALSE a cached element rendering is re-used 
	 * 
	 * @param boolean $force
	 */
	public function render($force = FALSE) {

		if(empty($this->html) || $force) {
			$this->html = sprintf(
				'<option value="%s"%s>%s</option>',
				$this->getValue(),
				$this->selected ? " selected='selected'" : '',
				$this->getLabel()
			);
		}

		return $this->html;

	}
}

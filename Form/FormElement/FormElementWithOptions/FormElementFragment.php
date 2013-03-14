<?php

namespace vxPHP\Form\FormElement\FormElementWithOptions;

use vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface;
use vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptionsInterface;

/**
 * abstract base class for form element fragments,
 * i.e. <option>s of <select> elements and single <input type="radio"> elements
 */

abstract class FormElementFragment implements FormElementFragmentInterface {

	protected	$name,
				$value,
				$label,
				$html,
				$parentElement,
				$selected = FALSE;

	/**
	 * creates a "fragment" for a form element with options and appends it to $formElement
	 *
	 * @param string $value
	 * @param string $name
	 * @param string $label
	 * @param FormElementWithOptionsInterface $formElement
	 */
	public function __construct($value, $name, $label, FormElementWithOptionsInterface $formElement = NULL) {
		$this->setValue($value);
		$this->setName($name);
		$this->setLabel($label);

		if(!is_null($formElement)) {
			$this->setParentElement($formElement);
		}
	}

	public function setValue($value) {
		$this->value = $value;
	}

	public function getValue() {
		return $this->value;
	}

	public function setName($name) {
		$this->name = $name;
	}

	public function getName() {
		return $this->name;
	}

	public function setLabel($label) {
		$this->label = $label;
	}

	public function getLabel() {
		return $this->label;
	}

	public function select() {
		$this->selected = TRUE;
	}

	public function unselect() {
		$this->selected = FALSE;
	}

	public function setParentElement(FormElementWithOptionsInterface $element) {
		$this->parentElement = $element;
	}
}

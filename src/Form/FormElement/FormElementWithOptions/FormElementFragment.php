<?php

namespace vxPHP\Form\FormElement\FormElementWithOptions;

use vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface;
use vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptionsInterface;

/**
 * abstract base class for form element fragments,
 * i.e. <option>s of <select> elements and single <input type="radio"> elements
 * 
 * @author Gregor Kofler
 * @version 0.4.0 2015-01-24 
 *
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

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface::setValue()
	 */
	public function setValue($value) {

		$this->value = $value;
		return $this;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface::getValue()
	 */
	public function getValue() {

		return $this->value;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface::setName()
	 */
	public function setName($name) {

		$this->name = $name;
		return $this;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface::getName()
	 */
	public function getName() {

		return $this->name;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface::setLabel()
	 */
	public function setLabel($label) {

		$this->label = $label;
		return $this;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface::getLabel()
	 */
	public function getLabel() {

		return $this->label;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface::select()
	 */
	public function select() {

		$this->selected = TRUE;
		return $this;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface::unselect()
	 */
	public function unselect() {

		$this->selected = FALSE;
		return $this;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface::setParentElement()
	 */
	public function setParentElement(FormElementWithOptionsInterface $element) {

		$this->parentElement = $element;
		return $this;

	}
}

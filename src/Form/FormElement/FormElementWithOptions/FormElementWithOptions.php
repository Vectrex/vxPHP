<?php

namespace vxPHP\Form\FormElement\FormElementWithOptions;

use vxPHP\Form\FormElement\FormElement;
use vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptionsInterface;

/**
 * abstract class for "complex" form elements,
 * i.e. <select> and <input type="radio"> elements
 */
abstract class FormElementWithOptions extends FormElement implements FormElementWithOptionsInterface {

	protected	$options = array(),
				$selectedOption;

	/**
	 * initalize element instance
	 * 
	 * @param string $name
	 * @param string $value
	 */
	public function __construct($name, $value = NULL) {
		
		parent::__construct($name, $value);

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElement::setValue()
	 */
	public function setValue($value = NULL) {

		if(isset($value)) {
			parent::setValue($value);
		}

		if(isset($this->selectedOption) && $this->selectedOption->getValue() != $this->getValue()) {
			$this->selectedOption->unselect();
			$this->selectedOption = NULL;
		}

		if(!isset($this->selectedOption)) {
			foreach($this->options as $o) {
				if($o->getValue() == $this->getValue()) {
					$o->select();
					$this->selectedOption = $o;
					break;
				}
			}
		}

		return $this;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptionsInterface::appendOption()
	 */
	public function appendOption(FormElementFragmentInterface $option) {

		$this->options[] = $option;
		$option->setParentElement($this);

		if($option->getValue() == $this->getValue()) {
			$option->select();
			if(isset($this->selectedOption)) {
				$this->selectedOption->unselect();
			}
			$this->selectedOption = $option;
		}

		return $this;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptionsInterface::createOptions()
	 */
	abstract public function createOptions(Array $options);
}

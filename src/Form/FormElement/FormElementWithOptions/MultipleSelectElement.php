<?php

namespace vxPHP\Form\FormElement\FormElementWithOptions;

/**
 * a select element of type multiple
 *
 * @author Gregor Kofler
 * @version 0.4.0 2015-01-24
 */
class MultipleSelectElement extends SelectElement {

	/**
	 * initialize element with name and value
	 * value can be string or array
	 * 
	 * @param string $name
	 * @param string|array $value
	 */
	public function __construct($name, $value = NULL) {

		parent::__construct($name, $value);

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\SelectElement::appendOption()
	 */
	public function appendOption(FormElementFragmentInterface $option) {

		$this->options[] = $option;
		$option->setParentElement($this);

		$v = $this->getValue();

		if(is_array($v) && in_array($option->getValue(), $v)) {
			$option->select();
		}
		else {
			$option->unselect();
		}

		return $this;

	}

	/**
	 * set value of select element
	 * value can be either a primitive or an array
	 * 
	 * @param mixed $value
	 */
	public function setValue($value = NULL) {

		if(isset($value)) {

			//ENT_QUOTES not set

			$this->value = array_map('htmlspecialchars', (array) $value);
		}

		foreach($this->options as $o) {

			$v = $this->getValue();

			if(is_array($v) && in_array($o->getValue(), $v)) {
				$o->select();
			}
			else {
				$o->unselect();
			}
		}
		
		return $this;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\SelectElement::render()
	 */
	public function render($force = FALSE) {

		$this->setAttribute('multiple', 'multiple');
		return parent::render($force);

	}
}

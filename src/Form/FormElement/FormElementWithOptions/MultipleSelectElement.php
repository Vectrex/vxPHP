<?php

namespace vxPHP\Form\FormElement\FormElementWithOptions;

class MultipleSelectElement extends SelectElement {

	public function __construct($name, $value = NULL) {
		parent::__construct($name, $value);
	}

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
	}

	/**
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
	}

	public function render($force = FALSE) {
		$this->setAttribute('multiple', 'multiple');
		return parent::render($force);
	}
}

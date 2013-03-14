<?php

namespace vxPHP\Form\FormElement\FormElementWithOptions;

use vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptions;
use vxPHP\Form\FormElement\FormElementWithOptions\RadioOptionElement;

class RadioElement extends FormElementWithOptions {

	public function __construct($name, $value = NULL) {
		parent::__construct($name, $value);
	}

	public function appendOption(FormElementWithOptions $option) {
		parent::appendOption($option);
	}

	public function createOptions(Array $options) {
		$this->options = array();
		foreach($options as $k => $v) {
			$this->appendOption(new RadioOptionElement($k, $v));
		}
	}

	public function render($force = FALSE) {
		if(empty($this->html) || $force) {
			$this->html = array();
			foreach($this->options as $o) {
				$this->html[] = $o->render();
			}
		}

		return $this->html;
	}
}

<?php

namespace vxPHP\Form\FormElement\FormElementWithOptions;

use vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptions;
use vxPHP\Form\FormElement\FormElementWithOptions\SelectOptionElement;

class SelectElement extends FormElementWithOptions {

	public function __construct($name, $value = NULL) {
		parent::__construct($name, $value);
	}

	public function appendOption(FormElementFragmentInterface $option) {
		parent::appendOption($option);
	}

	public function createOptions(Array $options) {
		$this->options = array();
		foreach($options as $k => $v) {
			$this->appendOption(new SelectOptionElement($k, $v, $this));
		}
		$this->setValue();
	}

	public function render($force = FALSE) {
		if(empty($this->html) || $force) {

			$attr = array();
			foreach($this->attributes as $k => $v) {
				$attr[] = "$k='$v'";
			}
			$html = array("<select name='{$this->getName()}' ".implode(' ', $attr).'>');

			foreach($this->options as $o) {
				$html[] = $o->render();
			}
			$html[] = '</select>';

			$this->html = implode("\n", $html);
		}

		return $this->html;
	}
}

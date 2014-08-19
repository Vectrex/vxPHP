<?php

namespace vxPHP\Form\FormElement;

use vxPHP\Form\FormElement\FormElement;

class TextareaElement extends FormElement {

	public function __construct($name, $value = NULL) {
		parent::__construct($name, $value);
	}

	public function render($force = FALSE) {
		if(empty($this->html) || $force) {
			$attr = array();
			foreach($this->attributes as $k => $v) {
				$attr[] = "$k='$v'";
			}
			$this->html = "<textarea name='{$this->getName()}' ".implode(' ', $attr).">{$this->getFilteredValue()}</textarea>";
		}

		return $this->html;
	}
}

<?php

namespace vxPHP\Form\FormElement;

use vxPHP\Form\FormElement\FormElement;

class TextareaElement extends FormElement {

	/**
	 * initialize a <textarea> element instance
	 * 
	 * @param unknown $name
	 * @param string $value
	 */
	public function __construct($name, $value = NULL) {

		parent::__construct($name, $value);

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElement::render()
	 */
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

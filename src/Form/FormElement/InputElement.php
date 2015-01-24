<?php

namespace vxPHP\Form\FormElement;

use vxPHP\Form\FormElement\FormElement;

class InputElement extends FormElement {

	/**
	 * initialize element with name and value
	 * 
	 * @param string $name
	 * @param string $value
	 */
	public function __construct($name, $value = NULL) {
		parent::__construct($name, $value);
	}

	/**
	 * return type of element
	 * 
	 * @return string
	 */
	public function getType() {
		if(!isset($this->attributes['type'])) {
			$this->attributes['type'] = 'text';
		}
		return $this->attributes['type'];
	}
	
	/**
	 * sets type of input element
	 * no validation of correct types is done ATM
	 * 
	 * @param string $type
	 * @return vxPHP\Form\FormElement\InputElement
	 */
	public function setType($type) {

		if(empty($type)) {
			$type = 'text';
		}

		$this->attributes['type'] = $type;
		
		return $this;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElement::render()
	 */
	public function render($force = FALSE) {

		if(empty($this->html) || $force) {
			if(!isset($this->attributes['type'])) {
				$this->attributes['type'] = 'text'; 
			}
			$attr = array();
			foreach($this->attributes as $k => $v) {
				$attr[] = "$k='$v'";
			}

			$this->html = "<input name='{$this->getName()}' value='{$this->getFilteredValue()}' ".implode(' ', $attr).'>';
		} 

		return $this->html;

	}
}

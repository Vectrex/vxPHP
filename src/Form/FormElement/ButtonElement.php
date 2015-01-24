<?php

namespace vxPHP\Form\FormElement;

use vxPHP\Form\FormElement\InputElement;

/**
 * button element
 * overwrites setType(), adds setInnerHTML() method to input element
 * 
 * @author gregor
 */
class ButtonElement extends InputElement {

	private	$innerHTML = '';
	
	/**
	 * initialize a <button> element instance
	 * 
	 * $type defaults to 'button'
	 * 
	 * @param string $name
	 * @param string $value
	 * @param string $type
	 */
	public function __construct($name, $value = NULL, $type = NULL) {

		parent::__construct($name, $value);

		if(isset($type)) {
			$this->setType($type);
		}
		else {
			$this->attributes['type'] = 'button';
		}
	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\InputElement::setType()
	 * 
	 * @return vxPHP\Form\FormElement\ButtonElement
	 */
	public function setType($type) {

		$type = strtolower($type);

		if(in_array($type, array('button', 'submit', 'reset'))) {
			$this->attributes['type'] = $type;
		}
		
		return $this;

	}

	/**
	 * set innerHTML of a button element
	 * 
	 * @param string $html
	 * @return \vxPHP\Form\FormElement\ButtonElement
	 */
	public function setInnerHTML($html) {

		$this->innerHTML = $html;
		return $this;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\InputElement::render()
	 */
	public function render($force = FALSE) {
		if(empty($this->html) || $force) {
			$attr = array();
			foreach($this->attributes as $k => $v) {
				$attr[] = "$k='$v'";
			}
			$this->html = "<button name='{$this->getName()}' value='{$this->getValue()}' ".implode(' ', $attr).">{$this->innerHTML}</button>";
		}

		return $this->html;
	}
}
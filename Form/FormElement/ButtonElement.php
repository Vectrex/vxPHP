<?php

namespace vxPHP\Form\FormElement;

use vxPHP\Form\FormElement\InputElement;

class ButtonElement extends InputElement {
	private	$innerHTML = '';
	
	public function __construct($name, $value = NULL, $type = NULL) {
		parent::__construct($name, $value);

		if(isset($type)) {
			$this->setType($type);
		}
		else {
			$this->attributes['type'] = 'button';
		}
	}

	public function setType($type) {
		$type = strtolower($type);

		if(in_array($type, array('button', 'submit', 'reset'))) {
			$this->attributes['type'] = $type;
		}
	}

	public function setInnerHTML($html) {
		$this->innerHTML = $html;
	}

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
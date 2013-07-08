<?php

namespace vxPHP\Form\FormElement;

use vxPHP\Form\FormElement\InputElement;

class CheckboxElement extends InputElement {
	private	$checked,
			$label;

	public function __construct($name, $value = NULL, $checked = FALSE, $label = NULL) {
		parent::__construct($name, $value);
		$this->setChecked($checked);
		$this->setLabel($label);
	}

	public function setChecked($state) {
		$this->checked = !!$state;
	}

	public function setLabel($label) {
		$this->label = $label;
	}

	public function getChecked() {
		return $this->checked;
	}

	public function render($force = FALSE) {
		if(empty($this->html) || $force) {
			if($this->checked) {
				$this->attributes['checked'] = 'checked';
			}
			else {
				unset($this->attributes['checked']);
			}
			$this->attributes['type'] = 'checkbox';

			$this->html = parent::render(TRUE).$this->label;
		}

		return $this->html;
	}
}
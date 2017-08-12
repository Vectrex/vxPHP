<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Form\FormElement;

/**
 * input element of type checkbox
 *
 * @version 0.8.0 2017-08-11
 * @author Gregor Kofler
 *
 */
class CheckboxElement extends InputElement {

	private	$checked,
			$label;


	/**
	 * inialize a <input type="checkbox"> element instance
	 * 
	 * @param string $name
	 * @param string $value
	 * @param boolean $checked
	 * @param string $label
	 */
	public function __construct($name, $value = NULL, $checked = FALSE, $label = NULL) {

		parent::__construct($name, $value);
		$this->setChecked($checked);
		$this->setLabel($label);

	}

	/**
	 * check checkbox
	 * 
	 * @param boolean $state
	 * @return \vxPHP\Form\FormElement\CheckboxElement
	 */
	public function setChecked($state) {
		
		$this->checked = !!$state;
		return $this;

	}

	/**
	 * set label displayed alongside checkbox
	 * 
	 * @param string $label
	 * @return \vxPHP\Form\FormElement\CheckboxElement
	 */
	public function setLabel($label) {

		$this->label = $label;
		return $this;

	}

	/**
	 * get checked state of checkbox
	 * 
	 * @return boolean
	 */
	public function getChecked() {

		return $this->checked;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\InputElement::render()
	 */
	public function render($force = FALSE) {

		if(empty($this->html) || $force) {

			if($this->checked) {
				$this->attributes['checked'] = 'checked';
			}
			else {
				unset($this->attributes['checked']);
			}
			$this->attributes['type'] = 'checkbox';

			if($this->label) {
				$formId = $this->form->getAttribute('id');
				$this->attributes['id'] = ($formId ? ($formId . '_')  : '') . $this->name . '_' . $this->value;
				$this->html = parent::render(TRUE) . sprintf('<label for="%s">%s</label>', $this->attributes['id'], $this->label);
						
			}
			else {
				$this->html = parent::render(TRUE);
			}

		}

		return $this->html;

	}
}
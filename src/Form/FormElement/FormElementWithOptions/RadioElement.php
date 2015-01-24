<?php

namespace vxPHP\Form\FormElement\FormElementWithOptions;

use vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptions;
use vxPHP\Form\FormElement\FormElementWithOptions\RadioOptionElement;

/**
 * a <input type="option"> element
 *
 * @author Gregor Kofler
 * @version 0.4.0 2015-01-24
 */
class RadioElement extends FormElementWithOptions {

	/**
	 * initialize element with both name and value
	 * 
	 * @param string $name
	 * @param string $value
	 */
	public function __construct($name, $value = NULL) {

		parent::__construct($name, $value);

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptions::createOptions()
	 */
	public function createOptions(Array $options) {

		$this->options = array();
		foreach($options as $k => $v) {
			$this->appendOption(new RadioOptionElement($k, $v));
		}
		
		return $this;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElement::render()
	 */
	public function render($force = FALSE) {

		if(empty($this->html) || $force) {
			$this->html = array();
			foreach($this->options as $o) {
				$this->html[] = $o->render();
			}
		}

		//@TODO flexible rendering of options

		return '<span>' . implode('</span><span>', $this->html) . '</span>';
	}
}

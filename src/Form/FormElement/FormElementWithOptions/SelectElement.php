<?php

namespace vxPHP\Form\FormElement\FormElementWithOptions;

use vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptions;
use vxPHP\Form\FormElement\FormElementWithOptions\SelectOptionElement;

/**
 * a select element
 *
 * @author Gregor Kofler
 * @version 0.4.0 2015-01-24
 */
class SelectElement extends FormElementWithOptions {

	/**
	 * initialize select element with both name and value
	 * 
	 * @param string $name
	 * @param string $value
	 */
	public function __construct($name, $value = NULL) {

		parent::__construct($name, $value);

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptions::appendOption()
	 */
	public function appendOption(FormElementFragmentInterface $option) {

		parent::appendOption($option);
		
		return $this;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptions::createOptions()
	 */
	public function createOptions(Array $options) {

		$this->options = array();
		foreach($options as $k => $v) {
			$this->appendOption(new SelectOptionElement($k, $v, $this));
		}
		$this->setValue();

		return $this;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElement::render()
	 */
	public function render($force = FALSE) {

		if(empty($this->html) || $force) {

			$attr = array();
			foreach($this->attributes as $k => $v) {
				$attr[] = sprintf('%s="%s"', $k, $v);
			}
			$html = array("<select name='{$this->getName()}' " . implode(' ', $attr).'>');

			foreach($this->options as $o) {
				$html[] = $o->render();
			}
			$html[] = '</select>';

			$this->html = implode("\n", $html);
		}

		return $this->html;
	}
}

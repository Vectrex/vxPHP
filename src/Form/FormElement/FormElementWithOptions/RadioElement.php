<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Form\FormElement\FormElementWithOptions;

use vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptions;
use vxPHP\Form\FormElement\FormElementWithOptions\RadioOptionElement;

/**
 * input element type "radio"
 *
 * @author Gregor Kofler
 */
class RadioElement extends FormElementWithOptions {

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

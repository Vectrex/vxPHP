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

use vxPHP\Form\FormElement\LabelElement;

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

		$this->options = [];

		foreach($options as $value => $label) {
			$this->appendOption(new RadioOptionElement($value, new LabelElement($label)));
		}
		
		return $this;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElement::render()
	 */
	public function render($force = false) {

		if(empty($this->html) || $force) {

			$this->html = [];

			foreach($this->options as $o) {
				$this->html[] = $o->render();
			}

		}

		//@TODO flexible rendering of options

		return '<span>' . implode('</span><span>', $this->html) . '</span>';
	}
}

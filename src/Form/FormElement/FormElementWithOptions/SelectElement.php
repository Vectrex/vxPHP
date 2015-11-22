<?php

namespace vxPHP\Form\FormElement\FormElementWithOptions;

use vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptions;
use vxPHP\Form\FormElement\FormElementWithOptions\SelectOptionElement;

/**
 * a select element
 *
 * @author Gregor Kofler
 */
class SelectElement extends FormElementWithOptions {

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

			$options = array();
			foreach($this->options as $o) {
				$options[] = $o->render();
			}

			$this->html = sprintf('<select name="%s" %s>%s</select>',
				$this->getName(),
				implode(' ', $attr),
				"\n" . implode("\n", $options) . "\n"
			);
		}

		return $this->html;
	}
}

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
	public function appendOption(FormElementFragmentInterface $option)
    {
		parent::appendOption($option);
		return $this;
	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptions::createOptions()
	 */
	public function createOptions(Array $options)
    {
		$this->options = [];

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
	public function render($force = false): string
    {
		if(empty($this->html) || $force) {

			$attr = [];
			foreach($this->attributes as $k => $v) {
				$attr[] = sprintf('%s="%s"', $k, $v);
			}

			$options = [];
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

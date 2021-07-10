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
	public function createOptions(Array $options): FormElementWithOptionsInterface
    {
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
	public function render($force = false): string
    {
		if(empty($this->html) || $force) {

            //@TODO flexible rendering of options

            $attr = [];

            foreach($this->attributes as $k => $v) {
                $attr[] = sprintf('%s="%s"', $k, $v);
            }

            $options = [];
            foreach($this->options as $o) {

                $options[] = sprintf(
                    "<span %s>%s</span>",
                    implode(' ', $attr),
                    $o->render()
                );
            }

            $this->html = implode("\n", $options);
		}
		return $this->html;
	}
}

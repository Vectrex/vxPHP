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

use vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragment;

/**
 * a single option belonging to a group of <input type="option"> elements
 * sharing the same name
 *
 * @author Gregor Kofler
 * @version 0.4.1 2015-11-14
 */
class RadioOptionElement extends FormElementFragment {

	/**
	 * initialize option with value, label and parent RadioElement
	 * 
	 * @param string $value
	 * @param string $label
	 * @param RadioElement $formElement
	 */
	public function __construct($value, $label, RadioElement $formElement = NULL) {

		parent::__construct($value, NULL, $label, $formElement);

	}

	/**
	 * render element; when $force is FALSE a cached element rendering is re-used 
	 * 
	 * @param string $force
	 */
	public function render($force = FALSE) {

		if(empty($this->html) || $force) {

			$this->html = sprintf(
				'<input name="%s" type="radio" value="%s"%s><label>%s</label>',
				$this->parentElement->getName(),
				$this->getValue(),
				$this->selected ? " checked='checked'" : '',
				$this->getLabel()
			);
		}

		return $this->html;

	}
}

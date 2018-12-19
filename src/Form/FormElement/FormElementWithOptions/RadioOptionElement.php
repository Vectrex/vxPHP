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
 * a single option belonging to a group of <input type="option"> elements
 * sharing the same name
 *
 * @author Gregor Kofler
 * @version 0.6.0 2018-12-12
 */
class RadioOptionElement extends FormElementFragment {

	/**
	 * initialize option with value, label and parent RadioElement
	 * 
	 * @param string $value
	 * @param LabelElement $label
	 * @param RadioElement $formElement
	 */
	public function __construct($value, LabelElement $label, RadioElement $formElement = null) {

		parent::__construct($value, null, $label, $formElement);

	}

	/**
	 * render element; when $force is FALSE a cached element rendering is re-used 
	 * 
	 * @param boolean $force
	 */
	public function render($force = false) {

		if(empty($this->html) || $force) {

			$formId = $this->parentElement->getForm()->getAttribute('id');
			$name = $this->parentElement->getName();
			$value = $this->getValue();
			$id = ($formId ? ($formId . '_') : '') . $name . '_' . $value;

			$this->html = sprintf(
				'<input id="%s" name="%s" type="radio" value="%s"%s>',
				$id,
				$name,
				$value,
				$this->selected ? " checked='checked'" : ''
			);

			if($this->label) {
			    $this->html .= $this->label->setAttribute('for', $id)->render();
            }

		}

		return $this->html;

	}
}

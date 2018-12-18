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
 * a single option of a select element
 * 
 * @author Gregor Kofler
 * @version 0.5.0 2018-12-15
 */
class SelectOptionElement extends FormElementFragment {

	/**
	 * initialize value and label and establish reference to SelectElement
	 * 
	 * @param string $value
	 * @param string $label
	 * @param SelectElement $formElement
	 */
	public function __construct($value, $label, SelectElement $formElement = null) {

		parent::__construct($value, null, new LabelElement($label), $formElement);

	}

	/**
	 * render option element; when $force is FALSE a cached element rendering is re-used 
	 * 
	 * @param boolean $force
     * @return string
	 */
	public function render($force = false) {

		if(empty($this->html) || $force) {
			$this->html = sprintf(
				'<option value="%s"%s>%s</option>',
				$this->getValue(),
				$this->selected ? " selected='selected'" : '',
				$this->getLabel()->getLabelText()
			);
		}

		return $this->html;

	}
}

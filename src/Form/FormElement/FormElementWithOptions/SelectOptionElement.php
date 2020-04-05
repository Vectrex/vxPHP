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
 * @version 0.8.1 2020-04-05
 */
class SelectOptionElement extends FormElementFragment {

	/**
	 * initialize value and label and establish reference to SelectElement
	 * 
	 * @param string $value
	 * @param string $label
	 * @param SelectElement $formElement
	 */
	public function __construct(string $value, string $label, SelectElement $formElement = null)
    {
		parent::__construct($value, new LabelElement($label), $formElement);
	}

	/**
	 * render option element; when $force is FALSE a cached element rendering is re-used 
	 * 
	 * @param boolean $force
     * @return string
	 */
	public function render($force = false): string
    {
        if(empty($this->html) || $force) {

            if($this->selected) {
                $this->attributes['selected'] = 'selected';
            }
            else {
                unset($this->attributes['selected']);
            }

            $this->attributes['value'] = $this->getValue();

            $attr = [];

            foreach($this->attributes as $k => $v) {
                $attr[] = sprintf('%s="%s"', $k, $v);
            }

            $this->html = sprintf(
				'<option %s>%s</option>',
                implode(' ', $attr),
				$this->getLabel()->getLabelText()
			);
		}

		return $this->html;
	}
}

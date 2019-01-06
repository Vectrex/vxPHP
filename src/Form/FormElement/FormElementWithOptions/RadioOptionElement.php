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
 * @version 0.8.0 2019-01-04
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

		parent::__construct($value, $label, $formElement);

	}

    /**
     * render element; when $force is FALSE a cached element rendering is re-used
     *
     * @param boolean $force
     * @return string
     * @throws \vxPHP\Application\Exception\ApplicationException
     * @throws \vxPHP\Template\Exception\SimpleTemplateException
     */
	public function render($force = false) {

		if(empty($this->html) || $force) {

            if($this->template) {

                parent::render();

            }

            else {

                $name = $this->parentElement->getName();
                $value = $this->getValue();

                if($this->selected) {
                    $this->attributes['checked'] = 'checked';
                }
                else {
                    unset($this->attributes['checked']);
                }

                if(!isset($this->attributes['id'])) {
                    $formId = $this->parentElement->getForm()->getAttribute('id');
                    $this->attributes['id'] = ($formId ? ($formId . '_') : '') . $name . '_' . $value;
                }

                $this->attributes['value'] = $this->getValue();
                $this->attributes['name'] = $this->parentElement->getName();

                $attr = [];

                foreach($this->attributes as $k => $v) {
                    $attr[] = sprintf('%s="%s"', $k, $v);
                }

                $this->html = sprintf(
                    '<input type="radio" %s>',
                    implode(' ', $attr)
                );

                if($this->label) {
                    $this->html .= $this->label->setAttribute('for', $this->attributes['id'])->render();
                }

            }

		}

		return $this->html;

	}
}

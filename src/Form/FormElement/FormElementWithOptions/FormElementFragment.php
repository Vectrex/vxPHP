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
 * abstract base class for form element fragments,
 * i.e. <option>s of <select> elements and single <input type="radio"> elements
 * 
 * @author Gregor Kofler
 * @version 0.6.0 2019-01-04
 *
 */

abstract class FormElementFragment implements FormElementFragmentInterface {

    /**
     * @var string
     */
	protected	$name;

    /**
     * @var string
     */
	protected $value;

	/**
     * @var LabelElement
     */
	protected $label;

	/**
     * @var string
     */
    protected $html;

    /**
     * @var FormElementWithOptionsInterface
     */
    protected $parentElement;

    /**
     * @var boolean
     */
    protected $selected = false;

    /**
     * @var array
     */
    protected $attributes = [];

	/**
	 * creates a "fragment" for a form element with options and appends it to $formElement
	 *
	 * @param string $value
	 * @param string $name
	 * @param LabelElement $label
	 * @param FormElementWithOptionsInterface $formElement
	 */
	public function __construct($value, $name, LabelElement $label, FormElementWithOptionsInterface $formElement = null) {

		$this->setValue($value);
		$this->setName($name);
		$this->setLabel($label);

		if(!is_null($formElement)) {
			$this->setParentElement($formElement);
		}
	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface::setValue()
	 */
	public function setValue($value) {

		$this->value = $value;
		return $this;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface::getValue()
	 */
	public function getValue() {

		return $this->value;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface::setName()
	 */
	public function setName($name) {

		$this->name = $name;
		return $this;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface::getName()
	 */
	public function getName() {

		return $this->name;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface::setLabel()
	 */
	public function setLabel(LabelElement $label) {

		$this->label = $label;
        return $this;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface::getLabel()
	 */
	public function getLabel() {

		return $this->label;

	}

    /**
     * (non-PHPdoc)
     * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface::setAttribute()
     */
    public function setAttribute($attribute, $value)
    {
        if(is_null($value)) {
            unset($this->attributes[$attribute]);
        }
        else {
            $this->attributes[$attribute] = $value;
        }

        return $this;
    }

    /**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface::select()
	 */
	public function select() {

		$this->selected = true;
		return $this;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface::unselect()
	 */
	public function unselect() {

		$this->selected = false;
		return $this;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface::setParentElement()
	 */
	public function setParentElement(FormElementWithOptionsInterface $element) {

		$this->parentElement = $element;
		return $this;

	}

}

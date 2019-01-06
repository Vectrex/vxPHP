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

use vxPHP\Form\FormElement\FormElement;

/**
 * abstract class for "complex" form elements,
 * i.e. <select> and <input type="radio"> elements
 */
abstract class FormElementWithOptions extends FormElement implements FormElementWithOptionsInterface {

	/**
	 * options of element
	 * 
	 * @var FormElementFragment[]
	 */
	protected $options = [];
	
	/**
	 * the selected option of element
	 * 
	 * @var FormElementFragment
	 */
	protected $selectedOption;

	/**
	 * initalize element instance
	 * 
	 * @param string $name
	 * @param string $value
	 */
	public function __construct($name, $value = null) {
		
		parent::__construct($name, $value);

	}

	/**
	 * {@inheritDoc}
	 * @see \vxPHP\Form\FormElement\FormElement::setValue()
	 */
	public function setValue($value = null) {

		if(isset($value)) {
			parent::setValue($value);
		}

		if(isset($this->selectedOption) && $this->selectedOption->getValue() != $this->getValue()) {
			$this->selectedOption->unselect();
			$this->selectedOption = null;
		}

		if(!isset($this->selectedOption)) {
			foreach($this->options as $o) {
				if($o->getValue() == $this->getValue()) {
					$o->select();
					$this->selectedOption = $o;
					break;
				}
			}
		}

		return $this;

	}

	/**
	 * {@inheritDoc}
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptionsInterface::appendOption()
	 */
	public function appendOption(FormElementFragmentInterface $option) {

		$this->options[] = $option;
		$option->setParentElement($this);

		if($option->getValue() == $this->getValue()) {
			$option->select();
			if(isset($this->selectedOption)) {
				$this->selectedOption->unselect();
			}
			$this->selectedOption = $option;
		}

		return $this;

	}

	/**
	 * {@inheritDoc}
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptionsInterface::getSelectedOption()
	 */
	public function getSelectedOption() {
		
		return $this->selectedOption;
		
	}
	
	/**
	 * {@inheritDoc}
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptionsInterface::createOptions()
	 */
	abstract public function createOptions(Array $options);
}

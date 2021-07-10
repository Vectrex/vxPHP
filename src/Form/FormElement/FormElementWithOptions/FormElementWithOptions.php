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
abstract class FormElementWithOptions extends FormElement implements FormElementWithOptionsInterface
{
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
	 * {@inheritDoc}
	 * @see \vxPHP\Form\FormElement\FormElement::setValue()
	 */
	public function setValue($value = null): FormElement
    {
        $this->value = $value;
        $v = (string) $value;

		if(isset($this->selectedOption) && $this->selectedOption->getValue() !== $v) {
			$this->selectedOption->unselect();
			$this->selectedOption = null;
		}

		if(!isset($this->selectedOption)) {
			foreach($this->options as $o) {
				if($o->getValue() === $v) {
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
	public function appendOption(FormElementFragmentInterface $option): FormElementWithOptionsInterface
    {
		$this->options[] = $option;
		$option->setParentElement($this);

		if($option->getValue() === (string) $this->getValue()) {
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
	public function getSelectedOption(): ?FormElementFragmentInterface
    {
		return $this->selectedOption;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptionsInterface::createOptions()
	 */
	abstract public function createOptions(Array $options): FormElementWithOptionsInterface;
}

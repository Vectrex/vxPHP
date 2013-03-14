<?php

namespace vxPHP\Form\FormElement\FormElementWithOptions;

use vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptionsInterface;

/**
 * abstract base class for form element fragments,
 * i.e. <option>s of <select> elements and single <input type="radio"> elements
 */

interface FormElementFragmentInterface {

	public function setValue($value);
	public function getValue();
	public function setName($name);
	public function getName();
	public function setLabel($label);
	public function getLabel();
	public function select();
	public function unselect();
	public function setParentElement(FormElementWithOptionsInterface $element);
}

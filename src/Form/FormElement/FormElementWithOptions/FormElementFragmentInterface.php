<?php

namespace vxPHP\Form\FormElement\FormElementWithOptions;

use vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptionsInterface;

/**
 * @author Gregor Kofler
 * @version 0.4.0 2015-01-24 
 * 
 * @todo check whether setName() or getName() is required
 */
interface FormElementFragmentInterface {

	/**
	 * set value of form element fragment
	 * 
	 * @param string $value
	 * @return vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface
	 */
	public function setValue($value);
	
	/**
	 * get fragment value
	 * 
	 * @return string
	 */
	public function getValue();

	/**
	 * set name of fragment
	 * 
	 * @param string $name
	 * @return vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface
	 */
	public function setName($name);

	/**
	 * get name of fragment
	 */
	public function getName();
	
	/**
	 * set label of fragment
	 * 
	 * @param string $label
	 * @return vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface
	 */
	public function setLabel($label);

	/**
	 * get label of fragment
	 * 
	 * @return string
	 */
	public function getLabel();

	/**
	 * select a fragment
	 * 
	 * @return vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface
	 */
	public function select();
	
	/**
	 * unselect a fragment
	 * 
	 * @return vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface
	 */
	public function unselect();

	/**
	 * link fragment to a form element (e.g. options to a <select> element)
	 * 
	 * @param FormElementWithOptionsInterface $element
	 * @return vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface
	 */
	public function setParentElement(FormElementWithOptionsInterface $element);
}

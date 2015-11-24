<?php
namespace vxPHP\Form\FormElement\FormElementWithOptions;

use vxPHP\Form\FormElement\FormElementInterface;
use vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface;

/**
 * @author Gregor Kofler
 * @version 0.4.0 2015-01-24 
 */
interface FormElementWithOptionsInterface extends FormElementInterface {

	/**
	 * append a form element fragment
	 * 
	 * @param FormElementFragmentInterface $option
	 * @return \vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptionsInterface
	 */
	public function appendOption(FormElementFragmentInterface $option);

	/**
	 * create an array of fragments
	 * 
	 * @param array $options
	 * @return \vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptionsInterface
	 */
	public function createOptions(Array $options);
	
	/**
	 * retrieve selected option
	 * 
	 * @return FormElementFragmentInterface
	 */
	public function getSelectedOption();
}

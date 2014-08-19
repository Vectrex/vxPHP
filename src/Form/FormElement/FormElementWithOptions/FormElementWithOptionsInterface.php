<?php
namespace vxPHP\Form\FormElement\FormElementWithOptions;

use vxPHP\Form\FormElement\FormElementInterface;
use vxPHP\Form\FormElement\FormElementWithOptions\FormElementFragmentInterface;

interface FormElementWithOptionsInterface extends FormElementInterface {

	public function appendOption(FormElementFragmentInterface $option);
	public function createOptions(Array $options);
}

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

use vxPHP\Form\FormElement\FormElementInterface;

/**
 * @author Gregor Kofler
 * @version 0.5.0 2021-07-10
 */
interface FormElementWithOptionsInterface extends FormElementInterface
{
	/**
	 * append a form element fragment
	 * 
	 * @param FormElementFragmentInterface $option
	 * @return \vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptionsInterface
	 */
	public function appendOption(FormElementFragmentInterface $option): FormElementWithOptionsInterface;

	/**
	 * create an array of fragments
	 * 
	 * @param array $options
	 * @return \vxPHP\Form\FormElement\FormElementWithOptions\FormElementWithOptionsInterface
	 */
	public function createOptions(Array $options): FormElementWithOptionsInterface;
	
	/**
	 * retrieve selected option
	 * 
	 * @return null|FormElementFragmentInterface
	 */
	public function getSelectedOption(): ?FormElementFragmentInterface;
}

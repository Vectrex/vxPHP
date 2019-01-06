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
use vxPHP\Template\SimpleTemplate;

/**
 * @author Gregor Kofler
 * @version 0.8.0 2019-01-06
 * 
 * @todo check whether setName() or getName() is required
 */
interface FormElementFragmentInterface {

	/**
	 * set value of form element fragment
	 * 
	 * @param string $value
	 * @return FormElementFragmentInterface
	 */
	public function setValue($value);
	
	/**
	 * get fragment value
	 * 
	 * @return string
	 */
	public function getValue();

	/**
	 * set label of fragment
	 * 
	 * @param LabelElement $label
	 * @return FormElementFragmentInterface
	 */
	public function setLabel(LabelElement $label);

	/**
	 * get label of fragment
	 * 
	 * @return LabelElement
	 */
	public function getLabel();

	/**
	 * select a fragment
	 * 
	 * @return FormElementFragmentInterface
	 */
	public function select();
	
	/**
	 * unselect a fragment
	 * 
	 * @return FormElementFragmentInterface
	 */
	public function unselect();

    /**
     * get selected status of fragment
     *
     * @return bool
     */
	public function getSelected();

    /**
     * set an attribute for a form element fragment
     *
     * @param string $attribute
     * @param string $value
     * @return FormElementFragmentInterface
     */
	public function setAttribute($attribute, $value);

	/**
	 * link fragment to a form element (e.g. options to a <select> element)
	 * 
	 * @param FormElementWithOptionsInterface $element
	 * @return FormElementFragmentInterface
	 */
	public function setParentElement(FormElementWithOptionsInterface $element);

    /**
     * get parent element the fragment belongs to
     *
     * @return FormElementWithOptionsInterface
     */
	public function getParentElement();

    /**
     * set a SimpleTemplate which is used when rendering the fragment
     *
     * @param SimpleTemplate $template
     * @return FormElementFragmentInterface
     */
    public function setSimpleTemplate(SimpleTemplate $template);

    /**
     * render the fragment using an optional SimpleTemplate
     *
     * @return string
     */
    public function render();

}

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
 * @version 0.8.1 2020-04-05
 */
interface FormElementFragmentInterface
{
	/**
	 * set value of form element fragment
	 * 
	 * @param string $value
	 * @return FormElementFragmentInterface
	 */
	public function setValue(string $value): self;
	
	/**
	 * get fragment value
	 * 
	 * @return string
	 */
	public function getValue(): string;

	/**
	 * set label of fragment
	 * 
	 * @param LabelElement $label
	 * @return FormElementFragmentInterface
	 */
	public function setLabel(LabelElement $label): self;

	/**
	 * get label of fragment
	 * 
	 * @return LabelElement
	 */
	public function getLabel(): LabelElement;

	/**
	 * select a fragment
	 * 
	 * @return FormElementFragmentInterface
	 */
	public function select(): self;
	
	/**
	 * unselect a fragment
	 * 
	 * @return FormElementFragmentInterface
	 */
	public function unselect(): self;

    /**
     * get selected status of fragment
     *
     * @return bool
     */
	public function getSelected(): bool;

    /**
     * set an attribute for a form element fragment
     *
     * @param string $attribute
     * @param string $value
     * @return FormElementFragmentInterface
     */
	public function setAttribute(string $attribute, string $value): self;

	/**
	 * link fragment to a form element (e.g. options to a <select> element)
	 * 
	 * @param FormElementWithOptionsInterface $element
	 * @return FormElementFragmentInterface
	 */
	public function setParentElement(FormElementWithOptionsInterface $element): self;

    /**
     * get parent element the fragment belongs to
     *
     * @return FormElementWithOptionsInterface
     */
	public function getParentElement(): FormElementWithOptionsInterface;

    /**
     * set a SimpleTemplate which is used when rendering the fragment
     *
     * @param SimpleTemplate $template
     * @return FormElementFragmentInterface
     */
    public function setSimpleTemplate(SimpleTemplate $template): self;

    /**
     * render the fragment using an optional SimpleTemplate
     *
     * @param bool $force
     * @return string
     */
    public function render(bool $force): string;
}

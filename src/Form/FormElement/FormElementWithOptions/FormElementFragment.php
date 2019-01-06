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
 * abstract base class for form element fragments,
 * i.e. <option>s of <select> elements and single <input type="radio"> elements
 * 
 * @author Gregor Kofler
 * @version 0.8.0 2019-01-06
 *
 */

abstract class FormElementFragment implements FormElementFragmentInterface {

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
     * template used for rendering the fragment
     * @var SimpleTemplate
     */
    protected $template;

	/**
	 * creates a "fragment" for a form element with options and appends it to $formElement
	 *
	 * @param string $value
	 * @param LabelElement $label
	 * @param FormElementWithOptionsInterface $formElement
	 */
	public function __construct($value, LabelElement $label, FormElementWithOptionsInterface $formElement = null)
    {
		$this->setValue($value);
		$this->setLabel($label);

		if(!is_null($formElement)) {
			$this->setParentElement($formElement);
		}
	}

	/**
	 * @see FormElementFragmentInterface::setValue()
     * @param string $value
     * @return $this|FormElementFragmentInterface
	 */
	public function setValue($value)
    {
		$this->value = $value;
		return $this;
	}

	/**
	 * @see FormElementFragmentInterface::getValue()
     * @return string
	 */
	public function getValue()
    {
		return $this->value;
	}

	/**
	 * @see FormElementFragmentInterface::setLabel()
     * @param LabelElement $label
     * @return $this|FormElementFragmentInterface
	 */
	public function setLabel(LabelElement $label)
    {
		$this->label = $label;
        return $this;
	}

	/**
	 * @see FormElementFragmentInterface::getLabel()
     * @return LabelElement
	 */
	public function getLabel()
    {
		return $this->label;
	}

    /**
     * @see FormElementFragmentInterface::setAttribute()
     * @param string $attribute
     * @param string $value
     * @return $this|FormElementFragmentInterface

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
	 * @see FormElementFragmentInterface::select()
     * @return $this|FormElementFragmentInterface
	 */
	public function select()
    {
    	$this->selected = true;
		return $this;
	}

	/**
	 * @see FormElementFragmentInterface::unselect()
     * @return $this|FormElementFragmentInterface
	 */
	public function unselect()
    {
		$this->selected = false;
		return $this;
	}

    /**
     *
     * @return bool
     */
	public function getSelected()
    {
        return $this->selected;
    }

	/**
	 * @see FormElementFragmentInterface::setParentElement()
     * @param FormElementWithOptionsInterface $element
     * @return $this|FormElementFragmentInterface
	 */
	public function setParentElement(FormElementWithOptionsInterface $element)
    {
		$this->parentElement = $element;
		return $this;
	}

    /**
     * @see FormElementFragmentInterface::getParentElement()
     * @return $this|FormElementWithOptionsInterface
     */
    public function getParentElement()
    {
        return $this->parentElement;
    }

    /**
     * set a SimpleTemplate which is then used when rendering
     * the fragment
     *
     * @param SimpleTemplate $template
     * @return $this
     */
    public function setSimpleTemplate(SimpleTemplate $template)
    {

        $this->template = $template;
        return $this;

    }

    /**
     * @see FormElementFragmentInterface::render()
     * @return string
     * @throws \vxPHP\Application\Exception\ApplicationException
     * @throws \vxPHP\Template\Exception\SimpleTemplateException
     */
	public function render()
    {
        if(!$this->template) {
            throw new \RuntimeException(sprintf("No template for fragment of element '%s' defined.", $this->parentElement->getName()));
        }

        $this->html = $this->template->assign('fragment', $this)->display();
        return $this->html;
    }

}

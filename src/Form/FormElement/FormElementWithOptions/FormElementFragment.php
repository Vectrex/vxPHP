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

use vxPHP\Application\Exception\ApplicationException;
use vxPHP\Form\FormElement\LabelElement;
use vxPHP\Template\Exception\SimpleTemplateException;
use vxPHP\Template\SimpleTemplate;

/**
 * abstract base class for form element fragments,
 * i.e. <option>s of <select> elements and single <input type="radio"> elements
 * 
 * @author Gregor Kofler
 * @version 0.8.1 2020-04-05
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
	public function __construct(string $value, LabelElement $label, FormElementWithOptionsInterface $formElement = null)
    {
		$this->setValue($value);
		$this->setLabel($label);

		if($formElement !== null) {
			$this->setParentElement($formElement);
		}
	}

	/**
	 * @see FormElementFragmentInterface::setValue()
     * @param string $value
     * @return $this|FormElementFragmentInterface
	 */
	public function setValue(string $value): FormElementFragmentInterface
    {
		$this->value = $value;
		return $this;
	}

	/**
	 * @see FormElementFragmentInterface::getValue()
     * @return string
	 */
	public function getValue(): string
    {
		return $this->value;
	}

	/**
	 * @see FormElementFragmentInterface::setLabel()
     * @param LabelElement $label
     * @return $this|FormElementFragmentInterface
	 */
	public function setLabel(LabelElement $label): FormElementFragmentInterface
    {
		$this->label = $label;
        return $this;
	}

	/**
	 * @see FormElementFragmentInterface::getLabel()
     * @return LabelElement
	 */
	public function getLabel(): LabelElement
    {
		return $this->label;
	}

    /**
     * @see FormElementFragmentInterface::setAttribute()
     * @param string $attribute
     * @param string $value
     * @return $this|FormElementFragmentInterface

     */
    public function setAttribute(string $attribute, string $value = null): FormElementFragmentInterface
    {
        if($value === null) {
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
	public function select(): FormElementFragmentInterface
    {
    	$this->selected = true;
		return $this;
	}

	/**
	 * @see FormElementFragmentInterface::unselect()
     * @return $this|FormElementFragmentInterface
	 */
	public function unselect(): FormElementFragmentInterface
    {
		$this->selected = false;
		return $this;
	}

    /**
     *
     * @return bool
     */
	public function getSelected(): bool
    {
        return $this->selected;
    }

	/**
	 * @see FormElementFragmentInterface::setParentElement()
     * @param FormElementWithOptionsInterface $element
     * @return $this|FormElementFragmentInterface
	 */
	public function setParentElement(FormElementWithOptionsInterface $element): FormElementFragmentInterface
    {
		$this->parentElement = $element;
		return $this;
	}

    /**
     * @see FormElementFragmentInterface::getParentElement()
     * @return FormElementWithOptionsInterface
     */
    public function getParentElement(): FormElementWithOptionsInterface
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
    public function setSimpleTemplate(SimpleTemplate $template): FormElementFragmentInterface
    {
        $this->template = $template;
        return $this;
    }

    /**
     * @see FormElementFragmentInterface::render()
     * @return string
     * @throws \RuntimeException
     * @throws ApplicationException
     * @throws SimpleTemplateException
     */
	public function render(bool $force = false): string
    {
        if(!$this->template) {
            throw new \RuntimeException(sprintf("No template for fragment of element '%s' defined.", $this->parentElement->getName()));
        }

        $this->html = $this->template->assign('fragment', $this)->display();
        return $this->html;
    }
}

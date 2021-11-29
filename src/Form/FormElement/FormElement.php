<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Form\FormElement;

use vxPHP\Application\Exception\ApplicationException;
use vxPHP\Constraint\ConstraintInterface;
use vxPHP\Form\HtmlForm;
use vxPHP\Template\Exception\SimpleTemplateException;
use vxPHP\Template\SimpleTemplate;

/**
 * abstract base class for "simple" form elements
 * 
 * @version 0.13.1 2021-11-29
 * @author Gregor Kofler
 * 
 */

abstract class FormElement implements FormElementInterface
{
	/**
	 * all validators (callbacks, regular expression, ConstraintInterfaces)
	 * that are applied when validating form element
	 * 
	 * @var array
	 */
	protected array $validators = [];

    /**
     * an error message when the form element fails to validate
     *
     * @var string|null
     */
    protected ?string $validationErrorMessage = null;

	/**
	 * all modifiers (callbacks, predefined function names, regular expressions)
	 * that are applied before a form element is validated
	 * 
	 * @var array
	 */
	protected array $modifiers = [];

	/**
	 * all attributes which will be rendered with the form element
	 * 
	 * @var array
	 */
	protected array $attributes = [];
	
	/**
	 * marks element as required when true
	 * and empty values will automatically
	 * invalidate the form element value
	 * 
	 * @var boolean
	 */
	protected bool $required = false;
	
	/**
	 * name of the element
	 * 
	 * @var string
	 */
	protected string $name;
	
	/**
	 * the value of the element
	 * 
	 * @var mixed
	 */
	protected $value;
	
	/**
	 * flag indicating that validators were passed
	 *
	 * @var bool|null
	 */
	protected ?bool $valid = null;
	
	/**
	 * stores reference to form once the element is assigned to
	 * 
	 * @var HtmlForm|null
     */
	protected ?HtmlForm $form = null;

    /**
     * label element of the form element
     *
     * @var LabelElement|null
     */
    protected ?LabelElement $label = null;

    /**
     * a template used for rendering the element
     *
     * @var SimpleTemplate|null
     */
    protected ?SimpleTemplate $template = null;

	/**
	 * the cached markup of the element
	 * 
	 * @var string
	 */
	protected string $html;

	/**
	 * initialize form element
	 * only name and an optional value are processed
	 * 
	 * @param string $name
	 * @param mixed $value
	 */
	public function __construct(string $name, $value = null)
    {
		$this->name = $name;
		$this->setValue($value);
	}

	/**
	 * set value of form element
	 *
	 * @param mixed $value
	 * @return FormElement
	 */
	public function setValue($value): self
    {
		$this->value = $value;
		return $this;
	}

	/**
	 * returns raw form element value
	 * 
	 * @return string|array|null
	 */
	public function getValue()
    {
		return $this->value;
	}

	/**
	 * returns modified form element value
	 * 
	 * @return string
	 */
	public function getModifiedValue(): string
    {
		return $this->applyModifiers((string) $this->value);
	}

	/**
	 * set name of form element
	 * 
	 * @param string $name
	 * @return FormElement
	 */
	public function setName(string $name): self
    {
		$this->name = $name;
		return $this;
	}

	/**
	 * returns name of form element
	 * 
	 * @return string $name
	 */
	public function getName(): string
    {
		return $this->name;
	}

    /**
     * assign label element to this form element
     * "for" attribute of label is set if form element has an "id" attribute
     *
     * @param LabelElement $label
     * @return FormElement
     */
    public function setLabel(LabelElement $label): self
    {
        $this->label = $label;

        if(($for = $this->getAttribute('id'))) {
            $label->setAttribute('for', $for);
        }

        return $this;
    }

    /**
     * get label element
     *
     * @return LabelElement
     */
    public function getLabel(): ?LabelElement
    {
        return $this->label;
    }

    /**
	 * sets miscellaneous attribute of form element
	 * attributes 'value', 'name' are treated by calling the according setters
     * setting 'id' will update 'for' when a label element is assigned
	 *  
	 * @param string $attributeName
	 * @param mixed $value
	 * @return FormElement
	 */
	public function setAttribute(string $attributeName, $value): self
    {
		$attributeName = strtolower($attributeName);

		if($attributeName === 'value') {
			return $this->setValue($value);
		}

		if($attributeName === 'name') {
			return $this->setName($value);
		}

		if($attributeName === 'id' && $this->label) {
		    $this->label->setAttribute('for', $value);
        }

		if(is_null($value)) {
			unset($this->attributes[$attributeName]);
		}
		else {
			$this->attributes[$attributeName] = $value;
		}
		return $this;
	}

	/**
	 * sets several attributes with an associative array
	 * 
	 * @param array $attributes
	 * @return FormElement
	 */
	public function setAttributes(Array $attributes): self
    {
		foreach($attributes as $k => $v) {
			$this->setAttribute($k, $v);
		}
		return $this;
	}

    /**
     * get a single attribute
     * name and value attributes are redirected to
     * the respective getter methods
     *
     * @param string $attributeName
     * @return string|null
     */
	public function getAttribute(string $attributeName): ?string
    {
	    $key = strtolower($attributeName);

	    if('value' === $key) {
	        return $this->getValue();
        }
        if('name' === $key) {
            return $this->getName();
        }
        return $this->attributes[$key] ?? null;
    }

	/**
	 * mark element as required
	 * disallow empty values when $required is TRUE
	 * 
	 * @param boolean $required
	 * @return FormElement
	 */
	public function setRequired(bool $required): self
    {
		$this->required = $required;
		return $this;
	}

	/**
	 * get required requirement of form element
	 * 
	 * @return boolean
	 */
	public function getRequired(): bool
    {
		return $this->required;
	}
	
	/**
	 * add a validator
	 * 
	 * validators can be either regular expressions,
	 * an anonymous function instance or a ConstraintInterface
	 * the FormElement::$valid flag is reset
	 * 
	 * @param mixed $validatingRule
	 * @return FormElement
	 */
	public function addValidator($validatingRule): self
    {
		$this->validators[] = $validatingRule;
		$this->valid = null;
		return $this;
	}

	/**
	 * set form to which an element is assigned
	 * automatically called by HtmlForm::addElement()
	 *
	 * @param HtmlForm $form
	 * @return FormElement
	 */
	public function setForm(HtmlForm $form): self
    {
		$this->form = $form;
		return $this;
	}

    /**
     * @return string
     */
    public function getValidationErrorMessage(): ?string
    {
        return $this->validationErrorMessage;
    }

    /**
     * get validation error message
     *
     * @param string $validationErrorMessage
     * @return FormElement
     */
    public function setValidationErrorMessage(string $validationErrorMessage): self
    {
        $this->validationErrorMessage = $validationErrorMessage;
        return $this;
    }

    /**
	 * add a modifier
	 * 
	 * modifiers can be an anonymous function or a string
	 * when the argument is a string it can either be a regular expression
	 * or a predefined term, which maps a PHP function
	 * currently 'trim', 'uppercase', 'lowercase', 'strip_tags' are supported
	 * 
	 * @param mixed $modifier
	 * @return FormElement
	 */
	public function addModifier($modifier): self
    {
		$this->modifiers[] = $modifier;
		return $this;
	}

	/**
	 * checks whether modified form element passes validation rules
	 * 
	 * @return boolean $success
	 */
	public function isValid(): bool
    {
		if(!isset($this->valid)) {
            $this->valid = $this->applyValidators(
                $this->applyModifiers((string) $this->getValue())
            );
		}

		return $this->valid;
	}

	/**
	 * check whether element can submit a form
	 * 
	 * @return boolean $result
	 */
	public function canSubmit(): bool
    {
		return
            ($this instanceof InputElement && isset($this->attributes['type']) && $this->attributes['type'] === 'submit') ||
			$this instanceof ImageElement ||
			$this instanceof SubmitInputElement ||
            ($this instanceof ButtonElement && $this->attributes['type'] === 'submit');
	}
	
	/**
	 * applies modifiers to FormElement::$value
	 * FormElement::$value remains unchanged
	 *
     * @param string $value
	 * @return string modified value
	 */
	protected function applyModifiers(string $value): string
    {
		foreach($this->modifiers as $modifier) {
			
			if($modifier instanceof \Closure) {
				$value = $modifier($value);
			}

			else {
				switch(strtolower($modifier)) {
					case 'trim':
                        $value = trim($value);
						break;

					case 'uppercase':
                        $value = strtoupper($value);
						break;
	
					case 'lowercase':
                        $value = strtolower($value);
						break;
	
					case 'strip_tags':
                        $value = strip_tags($value);
						break;
	
						// assume a regular expressions as fallback
					default:
                        $value = preg_replace($modifier, '', $value);
				}
			}
		}

		return $value;
	}

    /**
     * applies validators to modified FormElement::$value
     *
     * first checks whether a value is required,
     * then applies validators
     * checkbox elements are due to their nature handled seperately
     *
     * as soon as one validator fails
     * the result will yield FALSE
     *
     * and FormElement::$valid will be set accordingly
     *
     * @param string $value
     * @return bool
     */
    protected function applyValidators(string $value): bool
    {
        // first check whether form data is required
        // if not, then empty strings or null values are considered valid

        if(!$this->required && $value === '') {
            return true;
        }

        // handle a required checkbox separately

        if($this->required) {
            if ($this instanceof CheckboxElement) {
                if (!$this->getChecked()) {
                    return false;
                }
            } else if ($value === '') {
                return false;
            }
        }

        // fail at the very first validator that does not validate

        foreach($this->validators as $validator) {

            if($validator instanceof \Closure) {

                if(!$validator($value)) {
                    return false;
                }
            }

            else if($validator instanceof ConstraintInterface) {

                if(!$validator->validate($value)) {
                    return false;
                }
            }

            else if(!preg_match($validator, $value)) {
                return false;
            }
        }

        // assume validity when no previous validator failed

        return true;
    }

    /**
     * set a SimpleTemplate which is then used when rendering
     * the element
     *
     * @param SimpleTemplate $template
     * @return $this
     */
    public function setSimpleTemplate(SimpleTemplate $template): self
    {
        $this->template = $template;
        return $this;
    }

    /**
     * renders form element and returns markup
     * requires a template for rendering
     *
     * @return string
     * @throws ApplicationException
     * @throws SimpleTemplateException
     */
	public function render(): string
    {
        if(!$this->template) {
            throw new \RuntimeException(sprintf("No template for element '%s' defined.", $this->getName()));
        }

        $this->html = $this->template->assign('element', $this)->display();
        return $this->html;
    }
}

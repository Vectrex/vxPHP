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

use vxPHP\Form\FormElement\FormElementInterface;
use vxPHP\Form\FormElement\InputElement;
use vxPHP\Constraint\ConstraintInterface;

/**
 * abstract base class for "simple" form elements
 * 
 * @version 0.7.1 2016-11-28
 * @author Gregor Kofler
 * 
 */

abstract class FormElement implements FormElementInterface {

	/**
	 * all validators (callbacks, regular expression, ConstraintInterfaces)
	 * that are applied when validating form element
	 * 
	 * @var array
	 */
	protected $validators = [];
	
	/**
	 * all modifiers (callbacks, predefined function names, regular expressions)
	 * that are applied before a form element is validated
	 * 
	 * @var array
	 */
	protected $modifiers = [];

	/**
	 * all attributes which will be rendered with the form element
	 * 
	 * @var array
	 */
	protected $attributes = [];
	
	/**
	 * marks element as required when true
	 * and empty values will automatically
	 * invalidate the form element value
	 * 
	 * @var boolean
	 */
	protected $required;
	
	/**
	 * name of the element
	 * 
	 * @var string
	 */
	protected $name;
	
	/**
	 * the value of the element
	 * 
	 * @var mixed
	 */
	protected $value;
	
	/**
	 * flag indicating that validators were passed
	 * set by the applyValidators() method
	 * 
	 * @var bool
	 */
	protected $valid;
	
	/**
	 * the cached markup of the element
	 * 
	 * @var string
	 */
	protected $html;

	/**
	 * initialize form element
	 * only name and an optional value are processed
	 * 
	 * @param string $name
	 * @param mixed $value
	 */
	public function __construct($name, $value = NULL) {

		$this->name = $name;
		$this->setValue($value);

	}

	/**
	 * set value of form element
	 * htmlspecialchars() is applied for appropriate form element type
	 * 
	 * @param mixed $value
	 * @return vxPHP\Form\FormElement
	 */
	public function setValue($value) {

		if($this instanceof InputElement && !is_null($value) && (!isset($this->attributes['type']) || $this->attributes['type'] != 'submit')) {
			$value = htmlspecialchars($value, ENT_QUOTES);
		}
		$this->value = $value;

		return $this;

	}

	/**
	 * returns raw form element value
	 * 
	 * @return $raw_value
	 */
	public function getValue() {

		return $this->value;

	}

	/**
	 * returns modified form element value
	 * 
	 * @return $modified_value
	 */
	public function getModifiedValue() {

		return $this->applyModifiers();

	}

	/**
	 * set name of form element
	 * 
	 * @param string $name
	 * @return vxPHP\Form\FormElement
	 */
	public function setName($name) {

		$this->name = $name;
		return $this;

	}

	/**
	 * returns name of form element
	 * 
	 * @return string $name
	 */
	public function getName() {

		return $this->name;

	}
	
	/**
	 * sets miscellaneous attribute of form element
	 * attributes 'value', 'name' are treated by calling the according setters
	 *  
	 * @param string $attr
	 * @param mixed $value
	 * @return vxPHP\Form\FormElement
	 */
	public function setAttribute($attr, $value) {

		$attr = strtolower($attr);

		if($attr === 'value') {
			return $this->setValue($value);
		}

		if($attr === 'name') {
			return $this->setName($value);
		}

		if(is_null($value)) {
			unset($this->attributes[$attr]);
		}
		else {
			$this->attributes[$attr] = $value;
		}
		return $this;

	}

	/**
	 * sets several attributes with an associative array
	 * 
	 * @param array $attributes
	 * @return vxPHP\Form\FormElement
	 */
	public function setAttributes(Array $attributes) {

		foreach($attributes as $k => $v) {
			$this->setAttribute($k, $v);
		}
		return $this;

	}

	/**
	 * mark element as required
	 * disallow empty values when $required is TRUE
	 * 
	 * @param @boolen $required
	 * @return FormElement
	 */
	public function setRequired($required) {

		$this->required = (bool) $required;
		return $this;

	}

	/**
	 * get required requirement of form element
	 * 
	 * @return boolean
	 */
	public function getRequired() {

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
	 * @return vxPHP\Form\FormElement
	 */
	public function addValidator($validatingRule) {

		$this->validators[] = $validatingRule;
		$this->valid = NULL;
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
	 * @return vxPHP\Form\FormElement
	 */
	public function addModifier($modifier) {

		$this->modifiers[] = $modifier;
		return $this;

	}

	/**
	 * checks whether modified form element passes validation rules
	 * 
	 * @return boolean $success
	 */
	public function isValid() {

		if(!isset($this->valid)) {
			$this->applyValidators();
		}
		return $this->valid;

	}

	/**
	 * check whether element can submit a form
	 * 
	 * @return boolean $result
	 */
	public function canSubmit() {

		return 
			$this instanceof InputElement && isset($this->attributes['type']) && $this->attributes['type'] == 'submit' ||
			$this instanceof ImageElement ||
			$this instanceof SubmitInputElement ||
			$this instanceof ButtonElement && $this->attributes['type'] == 'submit';

	}
	
	/**
	 * applies modifiers to FormElement::$value
	 * FormElement::$value remains unchanged
	 * 
	 * @return string modified value
	 */
	protected function applyModifiers() {

		$v = $this->value;

		foreach($this->modifiers as $modifier) {
			
			if($modifier instanceof \Closure) {
				$v = $modifier($v);
			}

			else {
				switch(strtolower($modifier)) {
					case 'trim':
						$v = trim($v);
						break;

					case 'uppercase':
						$v = strtoupper($v);
						break;
	
					case 'lowercase':
						$v = strtolower($v);
						break;
	
					case 'strip_tags':
						$v = strip_tags($v);
						break;
	
						// assume a regular expressions as fallback
					default:
						$v = preg_replace($modifier, '', $v);
				}
			}
		}

		return $v;

	}

	/**
	 * applies validators to modified FormElement::$value
	 * 
	 * first checks whether a value is required,
	 * then applies validators
	 * 
	 * as soon as one validator fails 
	 * the result will yield FALSE
	 * 
	 * and FormElement::$valid will be set accordingly
	 */
	protected function applyValidators() {

		$value = $this->applyModifiers();

		// first check whether form data is required
		// if not, then empty strings or null values are considered valid

		if(
			!$this->required &&
			(
				$value === '' ||
				is_null($value)
			)
		) {
			$this->valid = TRUE;
			return;
		}

		// assume validity, in case no validators are set

		$this->valid = TRUE;
		
		// fail at the very first validator that does not validate

		foreach($this->validators as $validator) {
			
			if($validator instanceof \Closure) {
				
				if(!$validator($value)) {
					$this->valid = FALSE;
					return;
				}
			}

			else if($validator instanceof ConstraintInterface) {
				
				if(!$validator->validate($value)) {
					$this->valid = FALSE;
					return;
				}
			}

			else if(!preg_match($validator, $value)) {
				$this->valid = FALSE;
				return;
			}
		}

	}

	/**
	 * renders form element and returns markup
	 * 
	 * @return string
	 */
	protected abstract function render();
}

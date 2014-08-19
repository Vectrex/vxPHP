<?php

namespace vxPHP\Form\FormElement;

use vxPHP\Form\FormElement\FormElementInterface;
use vxPHP\Form\FormElement\InputElement;

/**
 * abstract base class for "simple" form elements
 * 
 * @version 0.3.5 2012-11-03
 * @author Gregor Kofler
 * 
 * @todo allow callbacks as validators and filters
 */

abstract class FormElement implements FormElementInterface {

	protected	$validators	= array(),
				$filters	= array(),
				$attributes	= array(),
				$name,
				$value,
				$valid,
				$html;

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
	 */
	public function setValue($value) {
		if($this instanceof InputElement && !is_null($value) && (!isset($this->attributes['type']) || $this->attributes['type'] != 'submit')) {
			$value = htmlspecialchars($value, ENT_QUOTES);
		}
		$this->value = $value;
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
	 * returns filtered form element value
	 * 
	 * @return $filtered_value
	 */
	public function getFilteredValue() {
		return $this->applyFilters();
	}

	/**
	 * set name of form element
	 * 
	 * @param string $name
	 */
	public function setName($name) {
		$this->name = $name;
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
	 * attributes 'value', 'name', 'disabled' are treated by calling the according setters
	 *  
	 * @param string $attr
	 * @param mixed $value
	 */
	public function setAttribute($attr, $value) {
		$attr = strtolower($attr);
		if($attr == 'value') {
			$this->setValue($value);
			return;
		}
		if($attr == 'name') {
			$this->setName($value);
		}
		if($attr == 'disabled') {
			!$value ? $this->enable() : $this->disable();
		}
		if(is_null($value)) {
			unset($this->attributes[$attr]);
		}
		else {
			$this->attributes[$attr] = $value;
		}
	}

	/**
	 * sets several attributes with an associative array
	 * 
	 * @param array $attributes
	 */
	public function setAttributes(Array $attributes) {
		foreach($attributes as $k => $v) {
			$this->setAttribute($k, $v);
		}
	}

	/**
	 * marks form element as disabled
	 */
	public function disable() {
		$this->attributes['disabled'] = 'disabled';
	}

	/**
	 * marks form element as enabled
	 */
	public function enable() {
		unset($this->attributes['disabled']);
	}

	/**
	 * adds a validator
	 * validators can be either regular expressions or callback functions
	 * the FormElement::$valid flag is reset
	 * 
	 * @param mixed $validating_rule
	 */
	public function addValidator($val) {
		$this->validators[] = $val;
		$this->valid = NULL;
	}
	
	/**
	 * adds a filter
	 * filters can either be a regular expression or a predefined term
	 * currently 'trim', 'uppercase', 'lowercase' are supported
	 * 
	 * @param string $filter
	 */
	public function addFilter($filter) {
		$this->filters[] = $filter;
	}

	/**
	 * checks whether filtered form element passes validation rules
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
	 * applies filters to FormElement::$value
	 * FormElement::$value remains unchanged
	 * 
	 * @return string filtered value
	 */
	protected function applyFilters() {

		$v = $this->value;

		foreach($this->filters as $f) {
			switch($f) {
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
					// allow regular expressions
				default:
					$v = preg_replace($f, '', $v);
			}
		}

		return $v;
	}

	/**
	 * applies validators to filtered FormElement::value
	 * and sets FormElement::$valid
	 */
	protected function applyValidators() {

		$value = $this->applyFilters();

		foreach($this->validators as $v) {
			if(is_string($v)) {
				if(!preg_match($v, $value)) {
					$this->valid = FALSE;
					return;
				}
			}
		}
		$this->valid = TRUE;
	}
	
	protected abstract function render();
}

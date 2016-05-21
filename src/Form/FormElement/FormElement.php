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

/**
 * abstract base class for "simple" form elements
 * 
 * @version 0.5.1 2015-11-25
 * @author Gregor Kofler
 * 
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
	 * attributes 'value', 'name', 'disabled' are treated by calling the according setters
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

		if($attr === 'disabled') {
			if($value) {
				return $this->disable();
			}
			else {
				return $this->enable();
			}
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
	 * marks form element as disabled
	 * 
	 * @return vxPHP\Form\FormElement
	 */
	public function disable() {

		$this->attributes['disabled'] = 'disabled';
		return $this;

	}

	/**
	 * marks form element as enabled
	 * 
	 * @return vxPHP\Form\FormElement
	 */
	public function enable() {

		unset($this->attributes['disabled']);
		return $this;

	}

	/**
	 * add a validator
	 * 
	 * validators can be either regular expressions or a \Closure instance
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
	 * add a filter
	 * 
	 * filters can be a \Closure instance or a string
	 * when filter is a string it can either be a regular expression
	 * or a predefined term, which maps PHP functions
	 * currently 'trim', 'uppercase', 'lowercase', 'strip_tags' are supported
	 * 
	 * @param mixed $filter
	 * @return vxPHP\Form\FormElement
	 */
	public function addFilter($filter) {

		$this->filters[] = $filter;
		return $this;

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
			
			if($f instanceof \Closure) {
				$v = $f($v);
			}

			else {
				switch(strtolower($f)) {
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
						$v = preg_replace($f, '', $v);
				}
			}
		}

		return $v;

	}

	/**
	 * applies validators to filtered FormElement::$value
	 * as soon as one validator fails
	 * the result will yield FALSE
	 * and FormElement::$valid will be set accordingly
	 */
	protected function applyValidators() {

		$value = $this->applyFilters();

		foreach($this->validators as $v) {
			
			if($v instanceof \Closure) {
				if(!$v($value)) {
					$this->valid = FALSE;
					return;
				}
			}

			else {
				if(!preg_match($v, $value)) {
					$this->valid = FALSE;
					return;
				}
			}
		}

		$this->valid = TRUE;

	}

	/**
	 * renders form element and returns markup
	 * 
	 * @return string
	 */
	protected abstract function render();
}

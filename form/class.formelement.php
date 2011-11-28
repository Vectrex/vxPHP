<?php
/**
 * classes for form elements
 *  
 * @version 0.3.3 2011-11-18
 * @author Gregor Kofler
 * 
 * @todo allow callbacks as validators and filters
 * @todo muliplecheckboxes
 */

/**
 * Factory for form elements
 * 
 * if $value is a scalar, the factory returns a single element,
 * if $value is an array, the factory returns a collection of elements
 *
 */
class FormElementFactory {

/**
 * create either single FormElement or array of FormElements
 *
 * @param string $type, type of element
 * @param string $name, name of element
 * @param mixed $value
 * @param array $attributes
 * @param array $options, array for initializing SelectOptionElements or RadioOptionElements
 * @param boolean $disabled
 * @param array $filters
 * @param array $validators
 *
 */
public static function create($type, $name, $value = NULL, array $attributes = array(), array $options = array(), $disabled = FALSE, array $filters = array(), array $validators = array()) {

		$type = strtolower($type);
		
		if(is_array($value) && $type != 'multipleselect') {
			$elem = self::createSingleElement($type, $name, NULL, $attributes, $options, $disabled, $filters, $validators);
			
			$elements = array();

			foreach($value as $k => $v) {
				$e = clone $elem;
				$e->setName(sprintf('%s[%s]', $name, $k));
				$e->setValue($v);
				$elements[$k] = $e;
			}

			unset($elem);
			return $elements;
		}

		else {
			return self::createSingleElement($type, $name, $value, $attributes, $options, $disabled, $filters, $validators);
		} 
	}

	private static function createSingleElement($type, $name, $value, $attributes, $options, $disabled, $filters, $validators) {

		switch($type) {
			case 'input':
				$elem = new InputElement($name);
				break;

			case 'password':
				$elem = new PasswordInputElement($name);
				break;

			case 'submit':
				$elem = new SubmitInputElement($name);
				break;
				
			case 'checkbox':
				$elem = new CheckboxElement($name);
				break;

			case 'textarea':
				$elem = new TextareaElement($name);
				break;

			case 'image':
				$elem = new ImageElement($name);
				break;

			case 'button':
				$elem = new ButtonElement($name);
				if(isset($attributes['type'])) {
					$elem->setType($attributes['type']);
				}
				break;

			case 'select':
				$elem = new SelectElement($name);
				$elem->createOptions($options);
				break;

			case 'radio':
				$elem = new RadioElement($name);
				$elem->createOptions($options);
				break;

			case 'multipleselect':
				$elem = new MultipleSelectElement($name);
				$elem->createOptions($options);
				break;

			default:
				throw new FormElementFactoryException("Unknown form element $type");
		}

		$elem->setAttributes($attributes);
		!$disabled ? $elem->enable() : $elem->disable();

		foreach($filters as $f) {
			$elem->addFilter($f);
		}

		foreach($validators as $v) {
			$elem->addValidator($v);
		}

		$elem->setValue($value);

		return $elem;
	}
}

class FormElementFactoryException extends Exception {
}


/**
 * abstract base class for "simple" form elements
 */
abstract class FormElement {
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

/**
 * abstract class for "complex" form elements,
 * i.e. <select> and <input type="radio"> elements
 */
abstract class FormElementWithOptions extends FormElement {

	protected	$options = array(),
				$selectedOption;

	public function __construct($name, $value = NULL) {
		parent::__construct($name, $value);
	}

	public function setValue($value = NULL) {

		if(isset($value)) {
			parent::setValue($value);
		}

		if(isset($this->selectedOption) && $this->selectedOption->getValue() != $this->getValue()) {
			$this->selectedOption->unselect();
			$this->selectedOption = NULL;
		}

		if(!isset($this->selectedOption)) {
			foreach($this->options as $o) {
				if($o->getValue() == $this->getValue()) {
					$o->select();
					$this->selectedOption = $o;
					break;
				}
			}
		}
	}

	public function appendOption(FormElementFragment $option) {

		$this->options[] = $option;
		$option->setParentElement($this);

		if($option->getValue() == $this->getValue()) {
			$option->select();
			if(isset($this->selectedOption)) {
				$this->selectedOption->unselect();
			}
			$this->selectedOption = $option;
		}
	}
}

class InputElement extends FormElement {

	public function __construct($name, $value = NULL) {
		parent::__construct($name, $value);
	}

	public function getType() {
		if(!isset($this->attributes['type'])) {
			$this->attributes['type'] = 'text';
		}
		return $this->attributes['type'];
	}
	
	/**
	 * sets type of input element
	 * no validation of correct types is done ATM
	 * 
	 * @param string $type
	 */
	public function setType($type) {
		if(empty($type)) {
			$type = 'text';
		}
		$this->attributes['type'] = $type;
	}

	public function render($force = FALSE) {
		if(empty($this->html) || $force) {
			if(!isset($this->attributes['type'])) {
				$this->attributes['type'] = 'text'; 
			}
			$attr = array();
			foreach($this->attributes as $k => $v) {
				$attr[] = "$k='$v'";
			}

			$this->html = "<input name='{$this->getName()}' value='{$this->getFilteredValue()}' ".implode(' ', $attr).'>';
		} 

		return $this->html;
	}
}

class PasswordInputElement extends InputElement {
	public function __construct($name, $value = NULL) {
		parent::__construct($name, $value);
	}

	public function render($force = FALSE) {

		if(empty($this->html) || $force) {
			$this->attributes['type'] = 'password';
			$this->html = parent::render(TRUE);
		}

		return $this->html;
	}
}

class SubmitInputElement extends InputElement {
	public function __construct($name, $value = NULL) {
		parent::__construct($name, $value);
	}

	public function render($force = FALSE) {

		if(empty($this->html) || $force) {
			$this->attributes['type'] = 'submit';
			$this->html = parent::render(TRUE);
		}

		return $this->html;
	}
}

class CheckboxElement extends InputElement {
	private	$checked,
			$label;

	public function __construct($name, $value = NULL, $checked = FALSE, $label = NULL) {
		parent::__construct($name, $value);
		$this->setChecked($checked);
		$this->setLabel($label);
	}
	
	public function setChecked($state) {
		$this->checked = !!$state;
	}

	public function setLabel($label) {
		$this->label = $label;
	}

	public function getChecked() {
		return $this->checked;
	}

	public function render($force = FALSE) {
		if(empty($this->html) || $force) {
			if($this->checked) {
				$this->attributes['checked'] = 'checked';
			}
			else {
				unset($this->attributes['checked']);
			}
			$this->attributes['type'] = 'checkbox';

			$this->html = parent::render(TRUE).$this->label;
		}
		
		return $this->html;
	}
}

class ImageElement extends InputElement {
	public function __construct($name, $value = NULL, $src) {
		parent::__construct($name, $value);
		$this->setAttribute('alt', pathinfo($src, PATHINFO_FILENAME));
	}
	
	public function render($force = FALSE) {
		$this->attributes['type'] = 'image';
		return parent::render($force);
	}
}

class ButtonElement extends InputElement {
	private	$innerHTML = '';
	
	public function __construct($name, $value = NULL, $type = NULL) {
		parent::__construct($name, $value);

		if(isset($type)) {
			$this->setType($type);
		}
		else {
			$this->attributes['type'] = 'button';
		}
	}

	public function setType($type) {
		$type = strtolower($type);

		if(in_array($type, array('button', 'submit', 'reset'))) {
			$this->attributes['type'] = $type;
		}
	}

	public function setInnerHTML($html) {
		$this->innerHTML = $html;
	}

	public function render($force = FALSE) {
		if(empty($this->html) || $force) {
			$attr = array();
			foreach($this->attributes as $k => $v) {
				$attr[] = "$k='$v'";
			}
			$this->html = "<button name='{$this->getName()}' value='{$this->getValue()}' ".implode(' ', $attr).">{$this->innerHTML}</button>";
		}

		return $this->html;
	}
}

class TextareaElement extends FormElement {

	public function __construct($name, $value = NULL) {
		parent::__construct($name, $value);
	}

	public function render($force = FALSE) {
		if(empty($this->html) || $force) {
			$attr = array();
			foreach($this->attributes as $k => $v) {
				$attr[] = "$k='$v'";
			}
			$this->html = "<textarea name='{$this->getName()}' ".implode(' ', $attr).">{$this->getFilteredValue()}</textarea>";
		}

		return $this->html;
	}
}

class SelectElement extends FormElementWithOptions {

	public function __construct($name, $value = NULL) {
		parent::__construct($name, $value);
	}

	public function appendOption(SelectOptionElement $option) {
		parent::appendOption($option);
	}

	public function createOptions(Array $options) {
		$this->options = array();
		foreach($options as $k => $v) {
			$this->appendOption(new SelectOptionElement($k, $v));
		}
		$this->setValue();
	}

	public function render($force = FALSE) {
		if(empty($this->html) || $force) {

			$attr = array();
			foreach($this->attributes as $k => $v) {
				$attr[] = "$k='$v'";
			}
			$html = array("<select name='{$this->getName()}' ".implode(' ', $attr).'>');
	
			foreach($this->options as $o) {
				$html[] = $o->render();
			}
			$html[] = '</select>';
			
			$this->html = implode("\n", $html); 
		}

		return $this->html;
	}
}

class MultipleSelectElement extends SelectElement {

	public function __construct($name, Array $value = array()) {
		parent::__construct($name, $value);
	}

	public function appendOption(FormElementFragment $option) {

		$this->options[] = $option;
		$option->setParentElement($this);

		if(in_array($option->getValue(), $this->getValue())) {
			$option->select();
		}
	}

	public function setValue(Array $value = NULL) {

		if(isset($value)) {
			//ENT_QUOTES not set
			$this->value = array_map('htmlspecialchars', $value);
		}

		foreach($this->options as $o) {
			if(in_array($o->getValue(), $this->getValue())) {
				$o->select();
			}
			else {
				$o->unselect();
			}
		}
	}

	public function render($force = FALSE) {
		$this->setAttribute('multiple', 'multiple');
		return parent::render($force);
	}
}

class RadioElement extends FormElementWithOptions {

	public function __construct($name, $value = NULL) {
		parent::__construct($name, $value);
	}

	public function appendOption(RadioOptionElement $option) {
		parent::appendOption($option);
	}

	public function createOptions(Array $options) {
		$this->options = array();
		foreach($options as $k => $v) {
			$this->appendOption(new RadioOptionElement($k, $v));
		}
	}

	public function render($force = FALSE) {
		if(empty($this->html) || $force) {
			$this->html = array();
			foreach($this->options as $o) {
				$this->html[] = $o->render();
			}
		}

		return $this->html;
	}
}

class MultipleCheckboxElement extends FormElementWithOptions {
	public function render() {
	}
}

/**
 * abstract base class for form element fragments,
 * i.e. <option>s of <select> elements and single <input type="radio"> elements
 */

abstract class FormElementFragment {

	protected	$name,
				$value,
				$label,
				$html,
				$parentElement,
				$selected = FALSE;
				
	public function __construct($value, $name, $label, $parentElement) {
		$this->setValue($value);
		$this->setName($name);
		$this->setLabel($label);
		$this->setParentElement($parentElement);
	}

	public function setValue($value) {
		$this->value = $value;
	}

	public function getValue() {
		return $this->value;
	}

	public function setName($name) {
		$this->name = $name;
	}

	public function getName() {
		return $this->name;
	}

	public function setLabel($label) {
		$this->label = $label;
	}

	public function getLabel() {
		return $this->label;
	}

	public function select() {
		$this->selected = TRUE;
	}

	public function unselect() {
		$this->selected = FALSE;
	}

	public function setParentElement($element) {
		$this->parentElement = $element;
	}
}

class SelectOptionElement extends FormElementFragment {

	public function __construct($value, $label, $parentElement = NULL) {
		parent::__construct($value, NULL, $label, $parentElement);
	}

	public function render($force = FALSE) {
		if(empty($this->html) || $force) {
			$selected = $this->selected ? " selected='selected'" : '';
			$this->html = "<option value='{$this->getValue()}'$selected>{$this->getLabel()}</option>";
		}

		return $this->html; 
	}
}

class RadioOptionElement extends FormElementFragment {

	public function __construct($value, $label, $parentElement = NULL) {
		parent::__construct($value, NULL, $label, $parentElement);
	}

	public function render($force = FALSE) {
		if(empty($this->html) || $force) {
			$checked = $this->selected ? " checked='checked'" : '';
			$this->html = "<input name='{$this->parentElement->getName()}' type='radio' value='{$this->getValue()}'$checked>{$this->getLabel()}";
		}

		return $this->html; 
	}
}

class FormElementException extends Exception {
}

class FormElementFragmentException extends Exception {
}
?>
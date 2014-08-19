<?php

namespace vxPHP\Form\FormElement;

use vxPHP\Form\Exception\FormElementFactoryException;

use vxPHP\Form\FormElement\InputElement;
use vxPHP\Form\FormElement\FormElementWithOptions\SelectElement;
use vxPHP\Form\FormElement\FormElementWithOptions\MultipleSelectElement;
use vxPHP\Form\FormElement\FormElementWithOptions\RadioElement;

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

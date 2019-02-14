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

use vxPHP\Form\Exception\FormElementFactoryException;

use vxPHP\Form\FormElement\FormElementWithOptions\SelectElement;
use vxPHP\Form\FormElement\FormElementWithOptions\MultipleSelectElement;
use vxPHP\Form\FormElement\FormElementWithOptions\RadioElement;

/**
 * Factory for form elements
 *
 * if $value is a scalar, the factory returns a single element,
 * if $value is an array, the factory returns a collection of elements
 *
 * @author Gregor Kofler
 * @version 0.5.1 2019-02-14
 */
class FormElementFactory {

    /**
     * create either single FormElement or array of FormElements
     *
     * @param string $type , type of element
     * @param string $name , name of element
     * @param mixed $value
     * @param array $attributes
     * @param array $options , array for initializing SelectOptionElements or RadioOptionElements
     * @param boolean $required
     * @param array $modifiers
     * @param array $validators
     * @param string $validationErrorMessage
     *
     * @return FormElement | FormElement[]
     * @throws FormElementFactoryException
     */
public static function create($type, $name, $value = null, array $attributes = [], array $options = [], $required = false, array $modifiers = [], array $validators = [], $validationErrorMessage = null) {

		$type = strtolower($type);

		if(is_array($value) && $type !== 'multipleselect') {
			$elem = self::createSingleElement($type, $name, null, $attributes, $options, $required, $modifiers, $validators, $validationErrorMessage);

			$elements = [];

			foreach($value as $k => $v) {
				$e = clone $elem;
				$e
					->setName(sprintf('%s[%s]', $name, $k))
					->setValue($v)
                ;
				$elements[$k] = $e;
			}

			unset($elem);
			return $elements;
		}

		else {
			return self::createSingleElement($type, $name, $value, $attributes, $options, $required, $modifiers, $validators, $validationErrorMessage);
		}
	}

    /**
     * generate a single form element
     *
     * @param $type
     * @param $name
     * @param $value
     * @param $attributes
     * @param $options
     * @param $required
     * @param $modifiers
     * @param $validators
     * @param $validationErrorMessage
     * @return ButtonElement|CheckboxElement|ImageElement|InputElement|PasswordInputElement|SubmitInputElement|TextareaElement
     *
     * @throws FormElementFactoryException
     */
	private static function createSingleElement($type, $name, $value, $attributes, $options, $required, $modifiers, $validators, $validationErrorMessage) {

		switch($type) {
			case 'input':
				$elem = new InputElement($name);
				break;

            case 'file':
                $elem = new FileInputElement($name);
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
				throw new FormElementFactoryException(sprintf("Unknown form element '%s''", $type));
		}

		$elem
			->setAttributes($attributes)
			->setRequired($required)
            ->setValidationErrorMessage($validationErrorMessage)
        ;

		foreach($modifiers as $modifier) {
			$elem->addModifier($modifier);
		}

		foreach($validators as $validator) {
			$elem->addValidator($validator);
		}

		$elem->setValue($value);

		return $elem;
	}
}

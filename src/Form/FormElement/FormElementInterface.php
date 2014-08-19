<?php

namespace vxPHP\Form\FormElement;

interface FormElementInterface {
	public function setValue($value);
	public function getValue();
	public function setName($name);
	public function getName();
	public function setAttribute($attributeName, $attributeValue);
}
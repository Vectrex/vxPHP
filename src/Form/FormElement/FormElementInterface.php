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

use vxPHP\Form\HtmlForm;

interface FormElementInterface {

	public function setValue($value);
	public function getValue();
	public function setName($name);
	public function getName();
	public function setAttribute($attributeName, $attributeValue);
/*
	public function getAttribute($attributeName);
	public function setRequired($required);
	public function getRequired();
	public function setForm(HtmlForm $form);
	public function getForm();
*/
}
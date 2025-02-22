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

use vxPHP\Template\SimpleTemplate;

interface FormElementInterface
{
    public function setValue($value);

    public function getValue();

    public function setName(string $name);

    public function getName();

    public function setAttribute(string $attributeName, $attributeValue);

    public function setLabel(LabelElement $label);

    public function getLabel();

    public function getAttribute(string $attributeName);

    public function setRequired(bool $required);

    public function getRequired();

    public function render();

    public function setSimpleTemplate(SimpleTemplate $template);
    /*
        public function setForm(HtmlForm $form);
        public function getForm();
    */
}
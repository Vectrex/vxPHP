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

/**
 * textarea element
 *
 * @author Gregor Kofler
 */
class TextareaElement extends FormElement {

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\FormElement::render()
	 */
	public function render($force = false): string
    {
		if(empty($this->html) || $force) {

			$attr = array();
			foreach($this->attributes as $k => $v) {
				$attr[] = "$k='$v'";
			}
			$this->html = sprintf('<textarea name="%s" %s>%s</textarea>',
				$this->getName(),
				implode(' ', $attr),
				$this->getModifiedValue()
			);
		}
		return $this->html;
	}
}

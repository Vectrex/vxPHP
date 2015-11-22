<?php

namespace vxPHP\Form\FormElement;

use vxPHP\Form\FormElement\FormElement;

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
	public function render($force = FALSE) {

		if(empty($this->html) || $force) {

			$attr = array();
			foreach($this->attributes as $k => $v) {
				$attr[] = "$k='$v'";
			}
			$this->html = sprintf('<textarea name="%s" %s>%s</textarea>',
				$this->getName(),
				implode(' ', $attr),
				$this->getFilteredValue()
			);

		}

		return $this->html;

	}
}

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
 * input element of type "password"
 * 
 * @author Gregor Kofler
 */
class PasswordInputElement extends InputElement
{
	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\InputElement::render()
	 */
	public function render($force = false): string
    {
		if(empty($this->html) || $force) {

			$this->attributes['type'] = 'password';
			$this->html = parent::render(true);

		}

		return $this->html;
	}
}

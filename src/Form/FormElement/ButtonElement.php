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
 * button element
 * overwrites setType(), adds setInnerHTML() method to input element
 * 
 * @author Gregor Kofler
 */
class ButtonElement extends InputElement {

	private	$innerHTML = '';
	
	/**
	 * initialize a <button> element instance
	 * 
	 * $type defaults to 'button'
	 * 
	 * @param string $name
	 * @param string $value
	 * @param string $type
	 */
	public function __construct($name, $value = NULL, $type = NULL) {

		parent::__construct($name, $value);

		if(isset($type)) {
			$this->setType($type);
		}
		else {
			$this->attributes['type'] = 'button';
		}
	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\InputElement::setType()
	 * 
	 * @return vxPHP\Form\FormElement\ButtonElement
	 */
	public function setType($type) {

		$type = strtolower($type);

		if(in_array($type, array('button', 'submit', 'reset'))) {
			parent::setType($type);
		}

		return $this;

	}

	/**
	 * set innerHTML of a button element
	 * 
	 * @param string $html
	 * @return \vxPHP\Form\FormElement\ButtonElement
	 */
	public function setInnerHTML($html) {

		$this->innerHTML = $html;
		return $this;

	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\InputElement::render()
	 */
	public function render($force = FALSE) {

		if(empty($this->html) || $force) {
			$attr = array();
			foreach($this->attributes as $k => $v) {
				$attr[] = "$k='$v'";
			}
			$this->html = sprintf(
				'<button name="%s" value="%s" %s>%s</button>',
				$this->getName(),
				$this->getValue(),
				implode(' ', $attr),
				$this->innerHTML
			);
		}

		return $this->html;
	}
}
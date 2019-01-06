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
 * generic input element
 *
 * @version 0.11.1 2018-01-06
 * @author Gregor Kofler
 */
class InputElement extends FormElement {

	/**
	 * initialize element with name and value
	 * 
	 * @param string $name
	 * @param string $value
	 */
	public function __construct($name, $value = null) {
		parent::__construct($name, $value);
	}

	/**
	 * return type of element
	 * 
	 * @return string
	 */
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
	 * @return \vxPHP\Form\FormElement\InputElement
	 */
	public function setType($type) {

		if(empty($type)) {
			$type = 'text';
		}

		$this->attributes['type'] = $type;
		
		return $this;

	}

	/**
	 * @see \vxPHP\Form\FormElement\FormElement::render()
     * @param bool $force
     * @return string
     * @throws \vxPHP\Application\Exception\ApplicationException
     * @throws \vxPHP\Template\Exception\SimpleTemplateException
	 */
	public function render($force = false) {

		if(empty($this->html) || $force) {

            if($this->template) {

                parent::render();
            }

            else {

                if(!isset($this->attributes['type'])) {
                    $this->attributes['type'] = 'text';
                }

                $attr = [];

                foreach($this->attributes as $k => $v) {
                    $attr[] = sprintf('%s="%s"', $k, $v);
                }


                $this->html = sprintf('<input name="%s" value="%s" %s>',
                    $this->getName(),
                    htmlspecialchars($this->getModifiedValue()),
                    implode(' ', $attr)
                );

            }

        }

		return $this->html;

	}
}

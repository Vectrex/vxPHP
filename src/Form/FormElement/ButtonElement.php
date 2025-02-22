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
class ButtonElement extends InputElement
{
	private	string $innerHTML = '';

    /**
     * initialize a <button> element instance
     *
     * $type defaults to 'button'
     *
     * @param string $name
     * @param string|null $value
     * @param string|null $type
     */
	public function __construct(string $name, ?string $value = null, ?string $type = null)
    {
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
     * @param string $type
     * @return ButtonElement
     * @see InputElement::setType
     *
     */
	public function setType(string $type): ButtonElement
    {
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
	 * @return ButtonElement
	 */
	public function setInnerHTML(string $html): ButtonElement
    {
		$this->innerHTML = $html;
		return $this;
	}

	/**
	 * (non-PHPdoc)
	 * @see InputElement::render
	 */
	public function render($force = false): string
    {
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
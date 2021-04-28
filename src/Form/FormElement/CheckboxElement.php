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
 * input element of type checkbox
 *
 * @version 0.11.1 2021-04-21
 * @author Gregor Kofler
 *
 */
class CheckboxElement extends InputElement {

    /**
     * @var bool
     */
	private $checked;

    /**
     * inialize a <input type="checkbox"> element instance
     *
     * @param string $name
     * @param mixed $value
     * @param boolean $checked
     * @param LabelElement|null $label
     */
	public function __construct(string $name, $value = null, bool $checked = false, LabelElement $label = null) {

		parent::__construct($name, $value);
		$this->setChecked($checked);

		if($label) {
            $this->setLabel($label);
        }
	}

	/**
	 * check checkbox
	 * 
	 * @param boolean $state
	 * @return \vxPHP\Form\FormElement\CheckboxElement
	 */
	public function setChecked(bool $state)
    {
		$this->checked = $state;
		return $this;
    }

    /**
	 * get checked state of checkbox
	 * 
	 * @return boolean
	 */
	public function getChecked(): bool
    {
		return $this->checked;
	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\InputElement::render()
	 */
	public function render($force = false): string
    {
		if(empty($this->html) || $force) {

			if($this->checked) {
				$this->attributes['checked'] = 'checked';
			}
			else {
				unset($this->attributes['checked']);
			}
			$this->attributes['type'] = 'checkbox';

            return parent::render(true);

		}

		return $this->html;
	}
}
<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Form\FormElement\FormElementWithOptions;

use vxPHP\Form\FormElement\FormElement;

/**
 * a select element of type multiple
 *
 * @author Gregor Kofler
 * @version 0.8.2 2021-07-10
 */
class MultipleSelectElement extends SelectElement
{
    /**
	 * @see SelectElement::appendOption()
     * @param FormElementFragmentInterface $option
     * @return $this|FormElementWithOptionsInterface|SelectElement
	 */
	public function appendOption(FormElementFragmentInterface $option): FormElementWithOptionsInterface
    {
		$this->options[] = $option;
		$option->setParentElement($this);

		$v = $this->getValue();

		if(is_array($v) && in_array($option->getValue(), $v)) {
			$option->select();
		}
		else {
			$option->unselect();
		}

		return $this;
	}

    /**
     * set value of select element
     * value can be either a primitive or an array
     *
     * @param mixed $value
     * @return MultipleSelectElement
     */
	public function setValue($value = null): FormElement
    {
		if(isset($value)) {

			//ENT_QUOTES not set

			$this->value = array_map('htmlspecialchars', (array) $value);
		}

		foreach($this->options as $o) {

			$v = $this->getValue();

			if(is_array($v) && in_array($o->getValue(), $v)) {
				$o->select();
			}
			else {
				$o->unselect();
			}
		}
		
		return $this;
	}

    /**
	 * @see SelectElement::render()
     * @param bool $force
     * @return string
     * @throws \vxPHP\Application\Exception\ApplicationException
     * @throws \vxPHP\Template\Exception\SimpleTemplateException
     */
	public function render($force = false): string
    {
        if(empty($this->html) || $force) {

            $attr = [];
            foreach($this->attributes as $k => $v) {
                $attr[] = sprintf('%s="%s"', $k, $v);
            }

            $options = [];
            foreach($this->options as $o) {
                $options[] = $o->render();
            }

            $this->html = sprintf('<select multiple="multiple" name="%s" %s>%s</select>',
                preg_replace('/\[\]$/', '', $this->getName()) . '[]',
                implode(' ', $attr),
                "\n" . implode("\n", $options) . "\n"
            );
        }

        return $this->html;
	}
}

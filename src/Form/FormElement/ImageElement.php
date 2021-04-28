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
 * input element of type "image"
 * 
 * @author Gregor Kofler
 */
class ImageElement extends InputElement {
	
	/**
	 * inialize a <input type="image"> element instance
	 * 
	 * @param string $name
	 * @param string $src
	 */
	public function __construct(string $name, string $src)
    {
		parent::__construct($name, $src);
		$this->setAttribute('alt', pathinfo($src, PATHINFO_FILENAME));
	}

	/**
	 * (non-PHPdoc)
	 * @see \vxPHP\Form\FormElement\InputElement::render()
	 */
	public function render($force = false): string
    {
        if(empty($this->html) || $force) {

            $attr = [
                sprintf('src="%s"', $this->getValue()),
                'type="image"'
            ];

            foreach($this->attributes as $k => $v) {

                if(in_array(strtolower($k), ['src', 'value', 'type'])) {
                    continue;
                }
                $attr[] = sprintf('%s="%s"', $k, $v);

            }

            $this->html = sprintf('<input name="%s" %s>',
                $this->getName(),
                implode(' ', $attr)
            );

        }

        return $this->html;
	}
}
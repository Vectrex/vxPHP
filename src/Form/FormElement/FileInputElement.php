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

class FileInputElement extends InputElement
{
    /**
     * initialize element with name and value
     *
     * @param string $name
     * @param string $value
     */
    public function __construct($name, $value = null)
    {
        parent::__construct($name, $value);
    }

    /**
     * return type of element
     *
     * @return string
     */
    public function getType()
    {
        return 'file';
    }

    public function getValue()
    {

    }

    public function setValue($value)
    {
        return $this;
    }

    /**
     * @see \vxPHP\Form\FormElement\FormElement::render()
     * @param bool $force
     * @return string
     * @throws \vxPHP\Application\Exception\ApplicationException
     * @throws \vxPHP\Template\Exception\SimpleTemplateException
     */
    public function render($force = false)
    {
        if(empty($this->html) || $force) {

            $this->attributes['type'] = 'file';

            if($this->template) {
                parent::render();
            }

            else {
                $attr = [];

                // value and files is blacklisted since setting both properties by the server is disabled in browsers

                foreach($this->attributes as $k => $v) {
                    if(!in_array($k, ['value', 'files'])) {
                        $attr[] = sprintf('%s="%s"', $k, $v);
                    }
                }

                $this->html = sprintf('<input name="%s" %s>',
                    $this->getAttribute('multiple') ? ($this->getName() . '[]') : $this->getName(),
                    implode(' ', $attr)
                );

            }

        }

        return $this->html;

    }


}
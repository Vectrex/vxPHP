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
 * class wrapping a label element
 *
 * @version 0.3.0 2018-12-15
 * @author Gregor Kofler
 *
 */

class LabelElement
{

    /**
     * all attributes which will be rendered with the attribute element
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * label text
     *
     * @var string
     */
    protected $labelText;

    /**
     * LabelElement constructor.
     * optionally assign a form element to this label
     *
     * @param string $labelText
     * @param array $attributes
     */
    public function __construct($labelText, array $attributes = [])
    {
        $this->labelText = $labelText;
        $this->attributes = $attributes;
    }

    /**
     * get the label text
     *
     * @return string
     */
    public function getLabelText(): string
    {
        return $this->labelText;
    }

    /**
     * set the label text
     *
     * @param string $labelText
     * @return LabelElement
     */
    public function setLabelText(string $labelText): LabelElement
    {
        $this->labelText = $labelText;
        return $this;
    }

    /**
     * sets attributes of form label
     *
     * @param string $attr
     * @param string $value
     * @return LabelElement
     */
    public function setAttribute($attr, $value)
    {

        $attr = strtolower($attr);

        if(is_null($value)) {
            unset($this->attributes[$attr]);
        }
        else {
            $this->attributes[$attr] = $value;
        }

        return $this;

    }

    /**
     * sets several attributes with an associative array
     *
     * @param array $attributes
     * @return LabelElement
     */
    public function setAttributes(Array $attributes)
    {

        foreach($attributes as $k => $v) {
            $this->setAttribute($k, $v);
        }
        return $this;

    }

    /**
     * render the label element
     * if a form element was assigned a
     * matching "for" attribute can be generated
     *
     * @return string
     */
    public function render()
    {

        $attr = [];

        foreach($this->attributes as $k => $v) {
            $attr[] = sprintf('%s="%s"', $k, $v);
        }

        return sprintf('<label %s>%s</label>',
            implode(' ', $attr),
            trim($this->labelText)
        );

    }

}
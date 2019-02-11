<?php
namespace vxPHP\Form\FormElement;


class FileInputElement extends InputElement
{
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

        return 'file';

    }


}
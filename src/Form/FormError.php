<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Form;

/**
 * A form error class
 * currently only wraps an error message
 *
 * @version 0.1.0 2018-01-20
 * @author Gregor Kofler
 *
 */
class FormError
{
    /**
     * @var string
     */
    private $errorMessage;

    /**
     * set error message
     *
     * @param string $errorMessage
     * @return $this
     */
    public function setErrorMessage(string $errorMessage = null)
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    /**
     * FormError constructor.
     *
     * @param $errorMessage
     */
    public function __construct($errorMessage = null)
    {
        $this->errorMessage = $errorMessage;
    }

    /**
     * get error message
     *
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

}
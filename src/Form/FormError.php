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
 * @version 0.1.1 2021-11-28
 * @author Gregor Kofler
 *
 */
class FormError
{
    /**
     * @var string|null
     */
    private ?string $errorMessage;

    /**
     * set error message
     *
     * @param string|null $errorMessage
     * @return $this
     */
    public function setErrorMessage(?string $errorMessage = null): FormError
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
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
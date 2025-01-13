<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Form\Exception;

class HtmlFormException extends \Exception
{
    const int TEMPLATE_FILE_NOT_FOUND = 1;
    const int NO_REQUEST_BOUND = 2;
    const int INVALID_METHOD = 3;
    const int INVALID_ENCTYPE = 4;
    const int CSRF_TOKEN_MISMATCH = 5;
    const int INVALID_MARKUP = 6;
}

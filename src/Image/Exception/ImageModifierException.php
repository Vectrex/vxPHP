<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Image\Exception;

class ImageModifierException extends \Exception
{
    public const FILE_NOT_FOUND = 1;
    public const WRONG_FILE_TYPE = 2;
    public const MISSING_PARAMETERS = 3;
    public const INVALID_PARAMETERS = 4;
}

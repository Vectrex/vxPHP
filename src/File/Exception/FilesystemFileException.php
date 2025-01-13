<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\File\Exception;

class FilesystemFileException extends \Exception
{
    public const int FILE_DOES_NOT_EXIST = 1;
    public const int FILE_RENAME_FAILED = 2;
    public const int FILE_DELETE_FAILED = 3;
    public const int METAFILE_CREATION_FAILED = 4;
    public const int METAFILE_ALREADY_EXISTS = 5;
}

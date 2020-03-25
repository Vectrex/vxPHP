<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Tests\Http\File;

use vxPHP\File\FilesystemFile;

class FakeFile extends FilesystemFile
{
    private $realpath;

    public function __construct($realpath)
    {
        $this->realpath = $realpath;
        $this->fileInfo = new FakeFileInfo();
    }

    public function getPath(): string
    {
        return $this->realpath;
    }

    public function getFilename(): string
    {
        return pathinfo($this->realpath, PATHINFO_BASENAME);
    }
}

class FakeFileInfo extends \SplFileInfo
{
    public function __construct()
    {

    }

    public function getSize()
    {
        return 42;
    }

    public function getMTime()
    {
        return time();
    }

    public function isReadable()
    {
        return true;
    }
}
<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\File;

use SplFileInfo;
use vxPHP\Application\Exception\ApplicationException;
use vxPHP\File\Exception\FilesystemFileException;

/**
 * interface for filesystem files and uploaded files
 *
 * @author Gregor Kofler
 *
 * @version 0.1.1 2021-05-29
 */
interface FilesystemFileInterface
{
    /**
     * retrieve file information provided by SplFileInfo object
     */
    public function getFileInfo(): SplFileInfo;

    /**
     * retrieve mime type
     * requires MimeTypeGetter
     *
     * @param bool $force forces re-read of mime type
     * @return string
     */
    public function getMimetype(bool $force = false): string;

    /**
     * check whether mime type indicates web image
     * (i.e. image/jpeg, image/gif, image/png, image/webp)
     *
     * @param bool $force forces re-read of mime type
     * @return bool
     */
    public function isWebImage(bool $force = false): bool;

    /**
     * retrieve filename
     */
    public function getFilename(): string;

    /**
     * retrieves physical path of file
     */
    public function getPath(): string;

    /**
     * returns path relative to assets path root
     *
     * @param boolean $force
     * @return string
     * @throws ApplicationException
     */
    public function getRelativePath(bool $force = false): string;

    /**
     * return filesystem folder of file
     */
    public function getFolder(): FilesystemFolder;

    /**
     * rename file
     *
     * @param string $to new filename
     * @return FilesystemFileInterface
     * @throws FilesystemFileException
     */
    public function rename(string $to): FilesystemFileInterface;

    /**
     * move file into new folder,
     * orphaned cache entries are deleted, new cache entries are not generated
     *
     * @param FilesystemFolder $destination
     * @return FilesystemFileInterface
     * @throws FilesystemFileException
     */
    public function move(FilesystemFolder $destination): FilesystemFileInterface;

    /**
     * deletes file and removes instance from lookup array
     * @throws FilesystemFileException
     */
    public function delete(): void;
}
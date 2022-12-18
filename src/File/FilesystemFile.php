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

use DirectoryIterator;
use SplFileInfo;
use vxPHP\Application\Exception\ApplicationException;
use vxPHP\File\Exception\FilesystemFileException;
use vxPHP\Observer\PublisherInterface;
use vxPHP\Util\Text;

/**
 * mapper for filesystem files
 *
 * @author Gregor Kofler
 *
 * @version 1.1.3 2021-12-05
 */

class FilesystemFile implements PublisherInterface, FilesystemFileInterface
{
    public const WEBIMAGE_MIMETYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /**
     * @var array
     */
    protected static array $instances = [];

    /**
     * @var string
     */
	protected string $filename;

    /**
     * @var FilesystemFolder
     */
    protected FilesystemFolder $folder;

    /**
     * @var string|null
     */
	protected ?string $mimetype = null;

    /**
     * @var SplFileInfo
     */
    protected \SplFileInfo $fileInfo;

    /**
     * @param string $path
     * @return FilesystemFile;
     * @throws FilesystemFileException
     * @throws Exception\FilesystemFolderException
     */
	public static function getInstance(string $path): FilesystemFile
    {
		if(!isset(self::$instances[$path])) {
			self::$instances[$path] = new self($path);
		}
		return self::$instances[$path];
	}

	public static function unsetInstance($path): void
    {
		if(isset(self::$instances[$path])) {
			unset(self::$instances[$path]);
		}
	}

    /**
     * constructs mapper for filesystem files
     * if folder is provided a bulk generation is assumed and certain checks are omitted
     *
     * @param string $path
     * @param FilesystemFolder|null $folder
     *
     * @throws Exception\FilesystemFolderException
     * @throws FilesystemFileException
     */
	public function __construct(string $path, FilesystemFolder $folder = null)
    {
		if($folder) {
			$path = $folder->getPath() . $path;
		}
		else {
			$path = realpath($path);
		}

		if(!file_exists($path)) {
			throw new FilesystemFileException(sprintf("File '%s' does not exist!", $path), FilesystemFileException::FILE_DOES_NOT_EXIST);
		}

		$this->folder = $folder ?: FilesystemFolder::getInstance(pathinfo($path, PATHINFO_DIRNAME));
		$this->filename = pathinfo($path, PATHINFO_BASENAME);
		$this->fileInfo = new SplFileInfo($path);
	}

	/**
	 * retrieve file information provided by SplFileInfo object
	 */
	public function getFileInfo(): SplFileInfo
    {
		return $this->fileInfo;
	}

    /**
     * retrieve mime type
     * requires MimeTypeGetter
     *
     * @param bool $force forces re-read of mime type
     * @return string
     */
	public function getMimetype(bool $force = false): string
    {
		if($this->mimetype === null || $force) {
			$this->mimetype = MimeTypeGetter::get($this->folder->getPath() . $this->filename);
		}
		return $this->mimetype;
	}

    /**
     * check whether mime type indicates web image
     * (i.e. image/jpeg, image/gif, image/png, image/webp)
     *
     * @param bool $force forces re-read of mime type
     * @return bool
     */
	public function isWebImage(bool $force = false): bool
    {
		if(!isset($this->mimetype) || $force) {
			$this->mimetype = MimeTypeGetter::get($this->folder->getPath() . $this->filename);
		}
		return in_array($this->mimetype, self::WEBIMAGE_MIMETYPES, true);
	}

	/**
	 * retrieve filename
	 */
	public function getFilename(): string
    {
		return $this->filename;
	}

	/**
	 * retrieves physical path of file
	 */
	public function getPath(): string
    {
		return $this->folder->getPath() . $this->filename;
	}

    /**
     * returns path relative to assets path root
     *
     * @param boolean $force
     * @return string
     * @throws ApplicationException
     */
	public function getRelativePath(bool $force = false): string
    {
        return $this->folder->getRelativePath($force) . $this->filename;
	}

	/**
	 * return filesystem folder of file
	 */
	public function getFolder(): FilesystemFolder
    {
		return $this->folder;
	}

	/**
	 * rename file
	 *
	 * @param string $to new filename
	 * @return FilesystemFileInterface
	 * @throws FilesystemFileException
	 */
	public function rename(string $to): FilesystemFileInterface
    {
		$from = $this->filename;

		// name is unchanged, nothing to do

		if($from !== $to) {

			$oldpath = $this->folder->getPath() . $from;
			$newpath = $this->folder->getPath() . $to;

			if(file_exists($newpath)) {
				throw new FilesystemFileException("Rename from '$oldpath' to '$newpath' failed. '$newpath' already exists.", FilesystemFileException::FILE_RENAME_FAILED);
			}
	
			if(@rename($oldpath, $newpath)) {

				$this->renameCacheEntries($to);

				// set new filename

				$this->filename = $to;

				// re-read fileinfo
				
				$this->fileInfo	= new SplFileInfo($newpath);

				self::$instances[$newpath] = $this;
				unset(self::$instances[$oldpath]);
			}
	
			else {
				throw new FilesystemFileException(sprintf("Rename from '%s' to '%s' failed.", $oldpath, $newpath), FilesystemFileException::FILE_RENAME_FAILED);
			}

		}
		
		return $this;
	}

	/**
	 * move file into new folder,
	 * orphaned cache entries are deleted, new cache entries are not generated
	 *
	 * @param FilesystemFolder $destination
	 * @return FilesystemFileInterface
	 * @throws FilesystemFileException
	 */
	public function move(FilesystemFolder $destination): FilesystemFileInterface
    {
		// already in destination folder, nothing to do

		if($destination !== $this->folder) {

			$oldpath = $this->folder->getPath() . $this->filename;
			$newpath = $destination->getPath() . $this->filename;
	
			if(@rename($oldpath, $newpath)) {

				$this->clearCacheEntries();
				
				// set new folder reference

				$this->folder = $destination;
	
				// re-read fileinfo

				$this->fileInfo	= new SplFileInfo($newpath);

				self::$instances[$newpath] = $this;
				unset(self::$instances[$oldpath]);

				// @todo: check necessity of chmod
	
				@chmod($newpath, 0666 & ~umask());
				
			}
	
			else {
				throw new FilesystemFileException(sprintf("Moving from '%s' to '%s' failed.", $oldpath, $newpath), FilesystemFileException::FILE_RENAME_FAILED);
			}

		}

		return $this;
	}

	/**
	 * updates names of cache entries
	 *
 	 * @param string $to new filename
	 */
	protected function renameCacheEntries(string $to): void
    {
		if(($cachePath = $this->folder->getCachePath(true))) {

			$di	= new DirectoryIterator($cachePath);

			foreach($di as $fileinfo) {

				$filename = $fileinfo->getFilename();

				if(	$fileinfo->isDot() ||
					!$fileinfo->isFile() ||
					strpos($filename, $this->filename) !== 0
				) {
					continue;
				}

				$renamed = substr_replace($filename, $to, 0, strlen($this->filename));
				rename($fileinfo->getRealPath(), $fileinfo->getPath() . DIRECTORY_SEPARATOR . $renamed);
			}
		}
	}

	/**
	 * deletes file and removes instance from lookup array
     *
	 * @throws FilesystemFileException
	 */
	public function delete(): void
    {
		if(@unlink($this->getPath())) {
			$this->deleteCacheEntries();
			self::unsetInstance($this->getPath());
		}
		else {
			throw new FilesystemFileException("Delete of file '{$this->getPath()}' failed.", FilesystemFileException::FILE_DELETE_FAILED);
		}
	}

	/**
	 * cleans up cache entries associated with
	 * "original" file
	 */
	protected function deleteCacheEntries(): void
    {
		if(($cachePath = $this->folder->getCachePath(true))) {

			$di	= new DirectoryIterator($cachePath);

			foreach($di as $fileinfo) {
				if(	$fileinfo->isDot() ||
					!$fileinfo->isFile() ||
					strpos($fileinfo->getFilename(), $this->filename) !== 0
				) {
					continue;
				}

				unlink($fileinfo->getRealPath());
			}
		}
	}

	/**
	 * remove all cache entries of file
	 */
	public function clearCacheEntries(): void
    {
		$this->deleteCacheEntries();
	}

	/**
	 * retrieve information about cached files
	 * @return array|bool information
	 */
	public function getCacheInfo()
    {
		if(($cachePath = $this->folder->getCachePath(true))) {
			$size = 0;
			$count = 0;

			$di	= new DirectoryIterator($cachePath);

			foreach($di as $fileinfo) {
				if(	$fileinfo->isDot() ||
					!$fileinfo->isFile() ||
					strpos($fileinfo->getFilename(), $this->filename) !== 0
				) {
					continue;
				}
				++$count;
				$size += $fileinfo->getSize();
			}
			return ['count' => $count, 'totalSize' => $size];
		}
		return false;
	}

    /**
     * return all filesystem files instances within a certain folder
     *
     * @param FilesystemFolder $folder
     * @return array filesystem files
     * @throws Exception\FilesystemFolderException
     * @throws FilesystemFileException
     */
	public static function getFilesystemFilesInFolder(FilesystemFolder $folder): array
    {
		$files = [];

		$glob = glob($folder->getPath() . '*', GLOB_NOSORT);

		if($glob !== false) {

			foreach($glob as $f) {
				if(!is_dir($f)) {
					if(!isset(self::$instances[$f])) {
						self::$instances[$f] = new self($f);
					}

					$files[] = self::$instances[$f];
				}
			}
		}

		return $files;
	}

	/**
	 * clean up $filename and avoid duplicate filenames within folder $dir
	 * the cleanup is simple and does not take reserved filenames into consideration
	 * (e.g. PRN or CON on Windows systems)
	 * @see https://msdn.microsoft.com/en-us/library/aa365247
	 *
	 * @param string $filename
	 * @param FilesystemFolder $dir
	 * @param integer $ndx starting index used in renamed file
	 * @return string
	 */
	public static function sanitizeFilename(string $filename, FilesystemFolder $dir, int $ndx = 2): string
    {
		// remove any characters which are not allowed in any file system

		$filename = Text::toSanitizedFilename($filename);

		if(!file_exists($dir->getPath() . $filename)) {
			return $filename;
		}

		$pathinfo = pathinfo($filename);

		$pathinfo['extension'] = !empty($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '';

		while(file_exists($dir->getPath() . sprintf('%s(%d)%s', $pathinfo['filename'], $ndx, $pathinfo['extension']))) {
			++$ndx;
		}

		return sprintf('%s(%d)%s', $pathinfo['filename'], $ndx, $pathinfo['extension']);
	}
}
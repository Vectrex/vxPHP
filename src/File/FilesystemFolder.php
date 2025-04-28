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

use vxPHP\Application\Exception\ApplicationException;
use vxPHP\File\Exception\FilesystemFolderException;
use vxPHP\Application\Application;

/**
 * mapper for filesystem folders
 *
 * @author Gregor Kofler
 *
 * @version 0.7.5 2025-04-28
 */

class FilesystemFolder
{
	public const string CACHE_PATH = '.cache';

	/**
	 * caches instances of folders
	 * @var FilesystemFolder[]
	 */
	private static array $instances = [];

	/**
	 * absolute path
	 * @var string
	 */
	private	string $path;
	
	/**
	 * flags presence of a cache folder
	 * @var boolean|null
	 */
	private ?bool $cacheFound = null;
	
	/**
	 * relative path with application assets path as root
	 * @var string|null
     */
	private ?string $relPath = null;
	
	/**
     * the folder containing the current instance
	 * @var FilesystemFolder|null|false
     */
	private FilesystemFolder|null|false $parentFolder = null;

    /**
     * get the filesystem folder instance belonging to a given path
     *
     * @param string $path
     * @return FilesystemFolder
     * @throws FilesystemFolderException
     */
	public static function getInstance(string $path): FilesystemFolder
    {
	    $realpath = realpath($path);

	    if($realpath === false) {
            throw new FilesystemFolderException(sprintf('Path %s does not exist or is no directory.', $path));
        }

        $realpath = rtrim($realpath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		if(!isset(self::$instances[$realpath])) {
			self::$instances[$realpath] = new self($realpath);
		}

		return self::$instances[$realpath];
	}

    /**
     * remove instance from lookup hash
     *
     * @param string $path
     */
	public static function unsetInstance(string $path): void
    {
		if(isset(self::$instances[$path])) {
			unset(self::$instances[$path]);
		}
	}

    /**
     * FilesystemFolder constructor.
     *
     * @param string $path
     * @throws FilesystemFolderException
     */
	private function __construct(string $path)
    {
		if(is_dir($path)) {
			$this->path = $path;
		}
		else {
			throw new FilesystemFolderException(sprintf('Directory %s does not exist or is no directory.', $path));
		}
	}

	/**
	 * returns path of filesystem folder
     *
     * @return string
	 */
	public function getPath(): string
    {
		return $this->path;
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
		if($this->relPath === null || $force) {
			$relPath = preg_replace('~^' . preg_quote(Application::getInstance()->getAbsoluteAssetsPath(), '~').'~', '', $this->path, -1, $replaced);
			$this->relPath = $replaced === 0 ? null : $relPath;
		}

		return $this->relPath;
	}

    /**
     * returns all FilesystemFile instances in folder
     * when $extension is specified, only files with extension are returned
     *
     * @param string | null $extension
     * @return FilesystemFile[]
     * @throws Exception\FilesystemFileException
     * @throws FilesystemFolderException
     */
	public function getFiles(?string $extension = null): array
    {
		$result = [];
		$glob = $this->path . '*';

		if($extension !== null) {
			$glob .= '.' . $extension;
		}

		foreach(array_filter(glob($glob), 'is_file') as $f) {
			$result[] = FilesystemFile::getInstance($f);
		}

		return $result;
	}

    /**
     * returns all FilesystemFolder instances in folder
     *
     * @return array
     * @throws FilesystemFolderException
     */
	public function getFolders(): array
    {
		$result = [];
		$files = glob($this->path . '*', GLOB_ONLYDIR);

		if($files) {
			foreach($files as $f) {
				$result[] = self::getInstance($f);
			}
		}

		return $result;
	}

    /**
     * return parent FilesystemFolder of current folder
     * returns NULL, when current folder is already the root folder
     *
     * @param boolean $force
     * @return null | FilesystemFolder
     * @throws FilesystemFolderException
     */
	public function getParentFolder(bool $force = false): ?FilesystemFolder
    {
		if($this->parentFolder === null || $force) {
			
			$parentPath = dirname($this->path);
			
			// flag parentFolder property, when $this is already the root folder
			
			if($parentPath === $this->path) {
				$this->parentFolder = false;
			}

			else {
				$this->parentFolder = self::getInstance($parentPath);
			}
		}

		// return NULL (instead of FALSE) when there is no parent folder

		return $this->parentFolder ?: null;
	}
	
	/**
	 * checks whether a FilesystemFolder::CACHE_PATH subfolder exists
	 *
	 * @param boolean $force
	 * @return boolean result
	 */
	public function hasCache(bool $force = false): bool
    {
		if($this->cacheFound === null || $force) {
			$this->cacheFound = is_dir($this->path . self::CACHE_PATH);
		}

		return $this->cacheFound;
	}

    /**
     * checks whether a FilesystemFolder::CACHE_PATH subfolder
     * returns path to subfolder when folder exists, undefined otherwise
     *
     * @param boolean $force
     * @return string|null
     */
	public function getCachePath(bool $force = false): ?string
    {
		if($this->hasCache($force)) {
			return $this->path . self::CACHE_PATH . DIRECTORY_SEPARATOR;
		}
		return null;
	}

	/**
	 * create a new subdirectory
	 * returns newly created FilesystemFolder object
	 *
	 * @param string $folderName
	 * @return FilesystemFolder
	 * @throws FilesystemFolderException
	 */
	public function createFolder(string $folderName): FilesystemFolder
    {
		// prefix folder path, when realpath fails (e.g. does not exist)

		if(!($path = realpath($folderName))) {
			$path = $this->path . $folderName;
		}
		
		// throw exception when $folderName cannot be established as subdirectory of current folder
		
		else if (!str_starts_with($path, $this->path)) {
			throw new FilesystemFolderException(sprintf("Folder %s cannot be created within folder %s.", $folderName, $this->path));
		}
		
		// recursively create folder(s) when path not already exists
		
		if(!is_dir($path)) {

			if(mkdir($path, 0777, true) || is_dir($path)) {
                chmod($path, 0777);
			}
			else {
                throw new FilesystemFolderException(sprintf("Folder %s could not be created!", $path));
			}

			$path = realpath($path);
		}

		return self::getInstance($path);
	}

	/**
	 * tries to create a FilesystemFolder::CACHE_PATH subfolder
	 *
	 * @return string
	 * @throws FilesystemFolderException
	 */
	public function createCache(): string
    {
		if($this->hasCache(true)) {
			return $this->path . self::CACHE_PATH . DIRECTORY_SEPARATOR;
		}

		if(!mkdir($concurrentDirectory = $this->path . self::CACHE_PATH) && !is_dir($concurrentDirectory)) {
			throw new FilesystemFolderException(sprintf('Cache folder %s could not be created!', $this->path . self::CACHE_PATH));
		}
        chmod($this->path.self::CACHE_PATH, 0777);
        return $this->path . self::CACHE_PATH . DIRECTORY_SEPARATOR;
	}

	/**
	 * empties the cache when found
	 *
	 * @param boolean $force
	 * @throws FilesystemFolderException
	 */
	public function purgeCache(bool $force = false): void
    {
		if(($path = $this->getCachePath($force))) {
			foreach(glob($path. '*') as $f) {
				if(!unlink($f)) {
					throw new FilesystemFolderException(sprintf('Cache folder %s could not be purged!', $this->path . self::CACHE_PATH));
				}
			}
		}
	}

	/**
	 * empties folder
	 * removes all files in folder and subfolders (including any cache folders)
	 *
	 * @throws FilesystemFolderException
	 */
	public function purge(): void
    {
		foreach(
			new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator(
					$this->path,
					\FilesystemIterator::SKIP_DOTS
				),
				\RecursiveIteratorIterator::CHILD_FIRST) as $f) {

			if ($f->isDir()) {
				if(!@rmdir($f->getRealPath())) {
					throw new FilesystemFolderException(sprintf('Filesystem folder %s could not be deleted!', $f->getRealPath()));
				}

				self::unsetInstance($f->getRealPath());
			}
		    else {
		    	if(!@unlink($f->getRealPath())) {
		    		throw new FilesystemFolderException(sprintf('Filesystem file %s could not be deleted!', $f->getRealPath()));
		    	}

		    	FilesystemFile::unsetInstance($f->getRealPath());
			}
		}
	}

	/**
	 * deletes folder (and any contained files and folders)
     * warning: any references to this instance still exists and will yield invalid results
	 *
	 * @throws FilesystemFolderException
	 */
	public function delete(): void
    {
		$this->purge();

		if(!@rmdir($this->path)) {
			throw new FilesystemFolderException(sprintf("Filesystem folder '%s' could not be deleted.", $this->path));
		}

		self::unsetInstance($this->path);
	}

	/**
     * rename folder
     * removes instance from lookup hash
     * and returns a new instance of the new filesystemfolder
     * warning: any references to the old instance still exists and will yield invalid results
     *
     * @param string $to
     * @return FilesystemFolder
     * @throws FilesystemFolderException
     */
	public function rename (string $to): FilesystemFolder
    {
        if(str_contains($to, DIRECTORY_SEPARATOR)) {
            throw new FilesystemFolderException(sprintf("'%s' contains invalid characters.", $to));
        }

        $parent = $this->getParentFolder();
        if (!$parent) {
            throw new FilesystemFolderException(sprintf("No parent folder found. Filesystem folder '%s' could not be renamed.", $this->path));
        }
        $newPath = $parent->getPath() . $to;
        $oldPath = $this->path;

        if(!@rename($oldPath, $newPath)) {
            throw new FilesystemFolderException(sprintf("Filesystem folder '%s' could not be renamed to '%s'.", $oldPath, $newPath));
        }
        self::unsetInstance($oldPath);
        return self::getInstance($newPath);
    }

    /**
     * move folder
     * removes instance from lookup hash
     * and returns a new instance of the new filesystemfolder
     * warning: any references to the old instance still exists and will yield invalid results
     *
     * @param FilesystemFolder $destination
     * @return FilesystemFolder
     * @throws FilesystemFolderException
     */
    public function move (FilesystemFolder $destination): FilesystemFolder
    {
        if(str_starts_with($destination->getPath(), $this->path)) {
            throw new FilesystemFolderException('Folder cannot be moved into itself or a contained subfolder.');
        }
        $newPath = $destination->getPath() . basename($this->path);
        $oldPath = $this->path;

        if(!@rename($oldPath, $newPath)) {
            throw new FilesystemFolderException(sprintf("Filesystem folder '%s' could not be moved to '%s'.", $oldPath, $newPath));
        }
        self::unsetInstance($oldPath);
        return self::getInstance($newPath);
    }
}


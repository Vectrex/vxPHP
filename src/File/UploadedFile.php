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

use vxPHP\File\FilesystemFile;
use vxPHP\File\Exception\FilesystemFileException;

/**
 * extend FilesystemFile to meet requirements of uploaded files
 * an UploadedFile behaves like a FilesystemFile except the first
 * move() moves the temporary file to its destination an re-populates
 * relevant properties (filename, fileInfo) with the new values
 * 
 * @author Gregor Kofler
 *
 * @version 1.0.0 2020-02-05
 */
class UploadedFile extends FilesystemFile
{
	/**
	 * the original file name
	 * 
	 * @var string
	 */
	private $originalName;
	
	/**
	 * when TRUE the file has already been uploaded
	 * a move() is then handled by parent class
	 * 
	 * @var boolean
	 */
	private $alreadyUploaded;

	public function __construct($path, $originalName)
    {
		$this->originalName = $originalName;
		parent::__construct($path);
	}

    /**
     * get the original file name of the uploaded file
     *
     * @return string
     */
	public function getOriginalName(): string
    {
		return $this->originalName;
	}

	/**
	 * renames an uploaded file by
	 * either overwriting the original filename (pre-move)
	 * or redirecting to parent method
	 * 
	 * (non-PHPdoc)
	 * @see \vxPHP\File\FilesystemFile::rename()
	 */
	public function rename($to): FilesystemFileInterface
    {
		if($this->alreadyUploaded) {
			return parent::rename($to);
		}

		$this->originalName = $to;

		return $this;
	}

	/**
	 * performs an initial move of an uploaded file
	 * from its temporary folder to a destination folder
	 * and renames it to the original filename
	 * once completed a flag is set and subsequent
	 * moves are redirected to the parent method
	 * 
	 * (non-PHPdoc)
	 * @see \vxPHP\File\FilesystemFile::move()
	 */
	public function move(FilesystemFolder $destination): FilesystemFileInterface
    {
		if($this->alreadyUploaded) {
			return parent::move($destination);
		}

		$oldpath = $this->folder->getPath() . $this->filename;
		$filename = self::sanitizeFilename($this->originalName, $destination);
		$newpath = $destination->getPath() . $filename;

		// ensure that only uploaded files are handled

		if(is_uploaded_file($oldpath)) {

			// move uploaded file

			if(@move_uploaded_file($oldpath, $newpath)) {

				// flag completed upload
				
				$this->alreadyUploaded = TRUE;

				// set new folder reference

				$this->folder = $destination;

				// set new filename

				$this->filename	= $filename;

				// re-read fileinfo

				$this->fileInfo	= new \SplFileInfo($newpath);

				// set cached instance

				self::$instances[$newpath] = $this;

				// @todo: check necessity of chmod
			
				@chmod($newpath, 0666 & ~umask());
				
			}

			else {
				throw new FilesystemFileException(sprintf("Could not move uploaded file '%s' to '%s'.", $this->originalName, $newpath), FilesystemFileException::FILE_RENAME_FAILED);
			}

		}
		
		else {
			throw new FilesystemFileException(sprintf("File '%s' was not identified as uploaded file.", $oldpath));
		}
		
		return $this;
	}
}
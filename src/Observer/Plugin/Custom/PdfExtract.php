<?php
/**
 * PDF Extract
 * @version 0.1.5, 2013-12-05
 * @author Gregor Kofler
 */

use vxPHP\Observer\Plugin\Plugin;
use vxPHP\Observer\SubjectInterface;
use vxPHP\File\MetaFile;
use vxPHP\Util\Uuid;
use vxPHP\Application\Application;

class PdfExtract extends Plugin {

	/**
	 * default values
	 * overwritten by plugin configuration
	 */
	protected	$table = 'pdfndx',
				$tmpDir,

				$observedFolders = array(),
				$hasThumb = TRUE,
				$thumbOfPage = 0,
				$thumbType = 'jpg',
				$thumbWidth = 150;

	private		$pdfToTextCommand,
				$convertCommand;

	/**
	 * constructor
	 * sets up temp dir,
	 * checks for binaries,
	 * primes database
	 *
	 * @throws PdfExtractException
	 */
  	public function __construct() {

  		if(is_null(Application::getInstance()->getConfig()->binaries)) {
			throw new PdfExtractException('Binaries not configured!');
		}

		$binaries = Application::getInstance()->getConfig()->binaries;

		if(
			!isset($binaries->executables['pdf_to_text']) ||
			!isset($binaries->executables['convert'])
		) {
			throw new PdfExtractException('Executables not configured!');
		}
		$this->pdfToTextCommand =
			$binaries->path .
			$binaries->executables['pdf_to_text']['file'];

		if(!file_exists($this->pdfToTextCommand)) {
			throw new PdfExtractException("Executable {$this->pdfToTextCommand} not found!");
		}

		$this->convertCommand =
			$binaries->path .
			$binaries->executables['convert']['file'];

		if(!file_exists($this->convertCommand)) {
			throw new PdfExtractException("Executable {$this->convertCommand} not found!");
		}

		$this->tmpDir = Application::getInstance()->getRootPath() . (defined('TMP_PATH') ? str_replace('/', DIRECTORY_SEPARATOR, ltrim(TMP_PATH, '/')) : 'tmp');

		if(!is_dir($this->tmpDir)) {
			if(!mkdir($this->tmpDir, 0777)) {
				throw new PdfExtractException("TMP_PATH not defined.");
			}
		}

		/*
		try {
			$this->db->execute("
				CREATE TABLE IF NOT EXISTS {$this->table} (
		  			`pdfndxID` int(11) NOT NULL AUTO_INCREMENT,
					`filesID` int(11) NOT NULL,
					`Page` int(11) DEFAULT NULL,
					`Content` mediumtext,
		  			first_created timestamp NOT NULL,
					PRIMARY KEY (`pdfndxID`),
					KEY `filesID` (`filesID`),
					FULLTEXT KEY `Content` (`Content`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8");
		}
		catch(MysqldbiException $e) {
			// perhaps no CREATE privileges
		}
		*/
	}

	public function configure(SimpleXMLElement $configXML) {
		parent::configure($configXML);

		foreach($this->observedFolders as &$f) {
			$f = rtrim($f, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		}
	}

	/**
	 * update() gets called once a metafile operation occurs
	 * extract and index single pages
	 */
	public function update(SubjectInterface $file) {

		if($file->getMimeType() != 'application/pdf') {
			return;
		}

		if(
			!in_array($file->getMetaFolder()->getFullPath(), $this->observedFolders) &&
			!in_array($file->getMetaFolder()->getRelativePath(), $this->observedFolders)
		) {
			return;
		}

		switch(Application::getInstance()->getEventDispatcher()->getEventType()) {

			case 'afterMetafileCreate':
				$this->extract($file);
				if($this->hasThumb == 1) {
					$this->createThumb($file);
				}
				break;

			case 'beforeMetafileDelete':
				$this->doPurge($file);
				break;
		}
	}

	/**
	 * extracts text of pdf page by page and
	 * writes text content to database
	 *
	 * @param MetaFile $file
	 * @return boolean
	 */
	private function extract(MetaFile $file) {
		$tmpName 	= Uuid::generate();

		$dest		= $this->tmpDir.$tmpName;
		$success	= FALSE;
		$page		= 0;
		$filename	= $file->getPath();

		// limit of 200 pages

		while($page++ < 200) {

			exec("{$this->pdfToTextCommand} -f $page -l $page '$filename' {$dest}_{$page}.txt");
			clearstatcache();

			// EOF reached

			if((!file_exists("{$dest}_{$page}.txt")) || (filesize("{$dest}_{$page}.txt") == 0)) {
				break;
			}

			if($this->createDbEntry($file->getId(), $page, "{$dest}_{$page}.txt")) {
				$success = TRUE;
				unlink("{$dest}_{$page}.txt");
			}
		}

		if(file_exists("{$dest}_{$page}.txt")) {
			unlink("{$dest}_{$page}.txt");
		}

		return $success;
	}

	/**
	 * create single db entry of indexed page
	 *
	 * @param int $metafilesID
	 * @param int $pageNdx
	 * @param string $temporaryFilename
	 *
	 * @return Boolean success
	 */
	private function createDbEntry($metafilesID, $pageNdx, $temporaryFilename) {

		return Application::getInstance()->getDb()->insertRecord($this->table,
			array(
				'filesID'	=> $metafilesID,
				'Page'		=> $pageNdx,
				'Content'	=> file_get_contents($temporaryFilename)
				//'Content'	=> iconv('ISO-8859-15', 'UTF-8', file_get_contents($temporaryFilename))
			));
	}

	/**
	 * create thumbnail of a single page of PDF file $file
	 *
	 * @param MetaFile $file
	 */
	public function createThumb(MetaFile $file) {
		$thumbFilename = $file->getMetaFolder()->getFilesystemFolder()->createCache().$file->getMetaFilename()."@page_{$this->thumbOfPage}.{$this->thumbType}";
		exec("{$this->convertCommand} -resize $this->thumbWidth -quality 90 -colorspace RGB -flatten -background white '{$file->getPath()}'[$this->thumbOfPage] '$thumbFilename'");
	}

	/**
	 * purges all db entries in table indexing file
	 *
	 * @param MetaFile $file
	 */
	private function doPurge(MetaFile $file) {
		Application::getInstance()->getDb()->deleteRecord($this->table, array('filesID' => $file->getId()));
	}
}

class PdfExtractException extends Exception {
}
?>
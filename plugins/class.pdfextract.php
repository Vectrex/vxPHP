<?php
/**
 * PDF Extract
 * @version 0.1.1, 2012-02-19
 * @author Gregor Kofler
 */

class PdfExtract extends Plugin implements EventListener {

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

	private		$db,
				$config,
				$eventDispatcher;

	/**
	 * constructor
	 * sets up temp dir,
	 * checks for binaries,
	 * primes database
	 * 
	 * @throws PdfExtractException
	 */
  	public function __construct() {
		$this->config			= &$GLOBALS['config'];
		$this->db				= &$GLOBALS['db'];
		$this->eventDispatcher	= &$GLOBALS['eventDispatcher'];

		if(!isset($this->config->binaries)) {
			throw new PdfExtractException('Binaries not configured!');
		}
		if(!isset($this->config->binaries->executables['pdf_to_text']) || !isset($this->config->binaries->executables['convert'])) {
			throw new PdfExtractException('Executables not configured!');
		}
		$file = $this->config->binaries->path.$this->config->binaries->executables['pdf_to_text']['file'];
		if(!file_exists($file)) {
			throw new PdfExtractException("Executable $file not found!");
		}
		$file = $this->config->binaries->path.$this->config->binaries->executables['convert']['file'];
		if(!file_exists($file)) {
			throw new PdfExtractException("Executable $file not found!");
		}

		$tmpDir	=	defined('TMP_PATH') ?
					(rtrim(TMP_PATH, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR) :
					rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR;

		if(!is_dir($tmpDir)) {
			if(!mkdir($tmpDir, 0777)) {
				throw new PdfExtractException("TMP_PATH not defined.");
			}
		}
		$this->tmpDir = $tmpDir;

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
			$f = rtrim($f, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
		}
	}

	/**
	 * update() gets called once a metafile operation occurs
	 * extract and index single pages
	 */
	public function update(Subject $file) {

		if($file->getMimeType() != 'application/pdf') {
			return;
		}

		if(
			!in_array($file->getMetaFolder()->getFullPath(), $this->observedFolders) &&
			!in_array($file->getMetaFolder()->getRelativePath(), $this->observedFolders)
		) {
			return;
		}

		switch($this->eventDispatcher->getEventType()) {

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
		$tmpName 	= UUID::generate();

		$dest		= $this->tmpDir.$tmpName;
		$success	= FALSE;
		$page		= 0;
		$filename	= $file->getPath();
		
		// limit of 200 pages

		while($page++ < 200) {

			exec("{$this->config->binaries->path}{$this->config->binaries->executables['pdf_to_text']['file']} -f $page -l $page '$filename' {$dest}_{$page}.txt");
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
		return $this->db->insertRecord($this->table,
			array(
				'filesID'	=> $metafilesID,
				'Page'		=> $pageNdx,
				'Content'	=> iconv('ISO-8859-15', 'UTF-8', file_get_contents($temporaryFilename)),
			));
	}
	
	/**
	 * create thumbnail of a single page of PDF file $file
	 * 
	 * @param MetaFile $file
	 */
	public function createThumb(MetaFile $file) {
		$thumbFilename = $file->getMetaFolder()->getFilesystemFolder()->createCache().$file->getMetaFilename()."@page_{$this->thumbOfPage}.{$this->thumbType}";
		exec("{$this->config->binaries->path}{$this->config->binaries->executables['convert']['file']} -resize $this->thumbWidth -quality 90 -colorspace RGB '{$file->getPath()}'[$this->thumbOfPage] '$thumbFilename'");
	}

	/**
	 * purges all db entries in table indexing file
	 *  
	 * @param MetaFile $file
	 */
	private function doPurge(MetaFile $file) {
		$this->db->deleteRecord($this->table, array('filesID' => $file->getId()));
	}
}

class PdfExtractException extends Exception {
}
?>
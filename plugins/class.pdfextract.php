<?php
/**
 * PDF Extract
 * @version 0.1.0, 2012-02-16
 * @author Gregor Kofler
 */

class PdfExtract extends Plugin implements EventListener {

	/**
	 * default values
	 * overwritten by plugin configuration
	 */
	private	$table = 'pdfndx',
			$tmpDir,

			$observedFolders = array(),
			$hasThumb = TRUE,
			$thumbOfPage = 0,
			$thumbType = 'jpg',
			$thumbWidth = 150;

	private $db,
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
					`Page` int(11) DEFAULT NULL,
					`Content` mediumtext,
		  			first_created timestamp NOT NULL,
					PRIMARY KEY (`pdfndxID`),
					KEY `metafilesID` (`metafilesID`),
					FULLTEXT KEY `Content` (`Content`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8");
		}
		catch(MysqldbiException $e) {
			// perhaps no CREATE privileges
		}
		*/
	}
	
	public function configure(SimpleXMLElement $configXML) {

		foreach($configXML->children() as $name => $value) {
			$pName = preg_replace_callback('/_([a-z])/', function ($match) { return strtoupper($match[1]); }, $name);
			if(property_exists($this, $pName)) {
				if(is_array($this->$pName)) {
					$this->$pName = preg_split('~\s*[,;:]\s*~', (string) $value);
				}
				else {
					$this->$pName = (string) $value;
				}
			}
		}
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
			case 'afterUpload':
				$this->extract($file);
				if($this->hasThumb) {
					$this->createThumb($file);
				}
				break;

			case 'beforeDelete':
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
	 * create thumbnail of a single page of PDF file $file
	 * 
	 * @param MetaFile $file
	 */
	public function createThumb(MetaFile $file) {
		$f = pathinfo($file->getMetaFilename(), PATHINFO_FILENAME);
		exec("{$this->config->binaries->path}{$this->config->binaries->executables['convert']['file']} -resize $this->thumbWidth -quality 90 -colorspace RGB '{$file->getFilename()}'[$this->thumbOfPage] '{$file->getMetaFolder()->getFilesystemFolder()->createCache()}$f.{$this->thumbType}'");
	}

	/**
	 * create DB entry
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
				'metafilesID'	=> $metafilesID,
				'Page'			=> $pageNdx,
				'Content'		=> iconv('ISO-8859-15', 'UTF-8', file_get_contents($temporaryFilename)),
			));
	}
}

class PdfExtractException extends Exception {
}
?>
<?php
/**
 * PDF Extract
 * @version 0.3.2, 2008-11-14
 * @author Gregor Kofler
 * 
 * @todo PDF identification via mime type
 * @todo tmp-Path in binaries section
 * @todo proper encoding conversion
 */

class pdfExtract {
	private	$pdfFile,
			$table,
			$tableField,
			$referenceID,
			$tmpDir,
			$tmpName,
			$page;
	const	DEFAULT_THUMB_WIDTH = 150;

  /**
   * Constructor
   * @return bool
   * @param string $pdfFile
   * @param int $referenceID
   * @param string $table
   * @param array $field
   */
  	public function __construct($pdfFile = null, $referenceID = null, $table = 'pdfndx', $field = array('Filename' => 'varchar(128)')) {
		$this->config	= &$GLOBALS['config'];
		$this->db		= &$GLOBALS['db'];

		if(!isset($this->config->binaries))								{ throw new Exception('Binaries not configured!'); }
		if(!isset($this->config->binaries->executables['pdf_to_text'])) { throw new Exception('Executables not configured!'); }
		if(!isset($this->config->binaries->executables['convert']))		{ throw new Exception('Executables not configured!'); }
		$file = $this->config->binaries->path.$this->config->binaries->executables['pdf_to_text']['file'];
		if(!file_exists($file))											{ throw new Exception("Executable $file not found!"); }
		$file = $this->config->binaries->path.$this->config->binaries->executables['convert']['file'];
		if(!file_exists($file))											{ throw new Exception("Executable $file not found!"); }
		
		if ($pdfFile != null) {
			if(!file_exists($pdfFile))								  		{ return false; }
			$tmp = explode('.', $pdfFile);
			if(count($tmp) < 2 || strtolower($tmp[count($tmp)-1]) != 'pdf')	{ return false; }
			$this->pdfFile = $pdfFile;
	    }

		$tmpDir	= defined('TMP_PATH') ? TMP_PATH : $_SERVER['DOCUMENT_ROOT'].'/tmp/';

		if(!is_dir($tmpDir)) {
			if(!mkdir($tmpDir, 0777)) {
				return false;
			}
		}
		$this->tmpDir			= $tmpDir;
		$this->referenceID		= $referenceID;
		$this->table			= $table;
		$this->tableField		= array_pop(array_keys($field));

		return !$this->db->execute("
			CREATE TABLE IF NOT EXISTS {$this->table} (
	  			pdfndxID int(11) NOT NULL auto_increment,
				`{$this->tableField}` {$field[$this->tableField]} default NULL,
				`Page` int(11) default NULL,
				`Content` mediumtext,
				`ReferenceID` int(11) default NULL,
	  			first_created timestamp(14) NOT NULL,
				PRIMARY KEY (`pdfndxID`),
				KEY `ReferenceID` (`ReferenceID`),
				FULLTEXT KEY `Content` (`Content`)
			) ENGINE=MyISAM");
	}

	/**
	 * doExtract
	 * @param int referenceID
	 * @return bool
	 */
	public function doExtract($referenceID = null) {
		$rnd			= gettimeofday();
		$this->tmpName 	= $rnd['sec'].$rnd['usec'];
		$this->page		= 0;
		$dest			= $this->tmpDir.$this->tmpName;

		$success		= false;
		$i			= 0;
		while($this->page++ < 200) {	// limit of 200 pages
			exec("{$this->config->binaries->path}{$this->config->binaries->executables['pdf_to_text']['file']} -f {$this->page} -l {$this->page} '{$this->pdfFile}' {$dest}_{$i}.txt");
			clearstatcache();
			if((!file_exists("{$dest}_{$i}.txt")) || (filesize("{$dest}_{$i}.txt") == 0)) { break; }
			if($this->createDbEntry(!empty($referenceID) ? (int) $referenceID : $this->referenceID, "{$dest}_{$i}.txt")) {
				$success = true;
				unlink("{$dest}_{$i}.txt");
			}
			$i++;
		}
		if(file_exists("{$dest}_{$i}.txt")) {
			@unlink("{$dest}_{$i}.txt");
		}
		return $success;
	}

	/**
	 * create thumbnail of a page
	 * @param string $imgGroup image upload group
	 * @param integer $page, default 0
	 * @param string $type created filetype, default 'jpeg'
	 */
	public function makeThumb($imgGroup, $page = 0, $type = 'jpeg') {
		if(!isset($this->config->uploadImages[$imgGroup])) { return; }
		if(!in_array($type, $this->config->uploadImages[$imgGroup]->allowedTypes)) { return; }
		foreach($this->config->uploadImages[$imgGroup]->sizes as $s) {
			$w = $s->width != 0 ? $s->width : self::DEFAULT_THUMB_WIDTH;
			$p = $s->path;
			$f = basename($this->pdfFile, '.pdf');
			$t = $type == 'jpeg' ? 'jpg' : $type;
			exec("{$this->config->binaries->path}{$this->config->binaries->executables['convert']['file']} -resize $w -quality 90 -colorspace RGB '{$this->pdfFile}'[$page] '$p$f.$t'");
		}
	}

	/**
	 * create DB entry
	 * @param int referenceID
	 * @return bool
	 */
	private function createDbEntry($ref, $fn) {
		return $this->db->insertRecord($this->table,
				array(
					$this->tableField => basename($this->pdfFile),
					'Page' => $this->page,
					'Content' => iconv('ISO-8859-15', 'UTF-8', file_get_contents($fn)),
					'ReferenceID' => $ref
				));
	}
}
?>

<?php
class dirFunctions {
	var $name;
	var $path;
	var $handle;

	var $subdirs		= array();
	var $subdirsDates	= array();
	var $subdirsCount;

	var $files			= array();
	var $filesDates		= array();
	var $filesSizes		= array();
	var $filesCount;

	/**
	 * Konstruktor
	 * @param string dir | default aktuelles Verzeichnis
	 */

	function dirFunctions ($dir = null) {
		if($dir == null) {
			$dir = '.';
		}
		if(!$this->handle = opendir($dir)) { return false; }

		$this->name = $dir;
		$this->path = realpath($dir);
		$this->refresh();
	}

	/**
	 * Refresh der Properties
	 * @return bool result
	 */
	function refresh() {
		if(!$this->handle) { return false; }

		clearstatcache();

		while (false !== ($f = readdir($this->handle))) {
			if(is_file($this->path.'/'.$f))	{
				$this->files[]		= $f;
				$this->filesDates[] = date('Ymdhis', filemtime($this->path.'/'.$f));
				$this->filesSizes[] = filesize($this->path.'/'.$f);
			}
			else {
				if($f != '.' && $f != '..') {
					$this->subdirs[] = $f;
					$this->subdirsDates[] = date('Ymdhis', filemtime($this->path.'/'.$f));
				}
			}
		}

		$this->filesCount	= count($this->files);
		$this->subdirsCount	= count($this->subdirs);
	}

	/**
	 * Fileeinträge nach Kriterium sortieren
	 * @param string $sort { 'name' | 'ext' | 'date' | 'size' }
	 * @return bool success
	 */
	function sortFiles($sort = '', $desc = false) {

		switch ($sort) {
			case 'ext':
				foreach ($this->files as $f) {
					$extdummy[] = ($e = substr(strrchr($f, '.'),1)) ? $e : '';
				}
				return array_multisort($extdummy, $desc ? SORT_DESC : SORT_ASC, $this->files, $this->filesSizes, $this->filesDates);
			case 'date':
				return array_multisort($this->filesDates, $desc ? SORT_DESC : SORT_ASC, $this->files, $this->filesSizes);
			case 'size':
				return array_multisort($this->filesSizes, $desc ? SORT_DESC : SORT_ASC, $this->files, $this->filesDates);
			case 'name':
			default:
				return array_multisort($this->files, $desc ? SORT_DESC : SORT_ASC, $this->filesSizes, $this->filesDates);
		}
	}

	/**
	 * Einfache Ermittlung der Dateigrößen
	 * @param string Pfad
	 * @param string Muster
	 * @return array (Filename => Size)
	 */
	function simpleFileSize($path, $pattern) {
		if(!is_dir($path)) { return false; }
		$files = glob($path.$pattern);

		$fs = array();

		foreach ($files as $f) {
			if(!is_dir($f)) {
				$fs[basename($f)] = filesize($f);
			}
		}
		return $fs;
	}
}
?>

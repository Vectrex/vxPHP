<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Webpage;

class PaginationMenu {

	private	$parameterName,
			$entriesPerPage,
			$currentOffset,
			$totalEntries,
			$numberOfPages,
			$template,
			$range,
			$document,
			$urlFragment;

	/**
	 *
	 * @param string $parameterName
	 * @param int $entriesPerPage
	 * @param int $totalEntries
	 * @param int $range
	 * @param string $template
	 */
	public function __construct($url, $parameterName = 'offset', $entriesPerPage = NULL, $totalEntries = NULL, $range = NULL, $template = NULL) {

		$this->setParameterName($parameterName);
		$this->setEntriesPerPage($entriesPerPage);
		$this->setTotalEntries($totalEntries);
		$this->setRange($range);
		$this->setTemplate($template);
		$this->initializeUrl($url);

	}


	public function setTotalEntries($totalEntries) {
		$this->totalEntries = $totalEntries;
		$this->setNumberOfPages();
	}

	public function setEntriesPerPage($entriesPerPage) {
		$this->entriesPerPage = $entriesPerPage;
		$this->setNumberOfPages();
	}

	public function setParameterName($parameterName) {
		$this->parameterName = $parameterName;
	}

	public function setTemplate($template) {
		$this->template	= $template;
	}

	public function setRange($range) {
		$this->range = $range;

	}

	public function getNumberOfPages() {
		return $this->numberOfPages;
	}

	public function render() {

		$this->initializeUrl();
		$this->getCurrentOffset();

		$pageLinks	= array();

		if(empty($this->range)) {

			for($i = 1; $i <= $this->numberOfPages; ++$i) {
				$pageLinks[] = $i != $this->currentOffset ? \vxPHP\Template\SimpleTemplate::a($this->urlFragment.$i, $i) : "<span class='currentPage'>$i</span>";
			}

			if(is_string($separator))							{ return implode($separator, $pageLinks); }
			if(is_array($separator) && count($separator) == 5)	{ return implode($separator[2], $pageLinks); }
			return implode(' | ', $pageLinks);
		}

		else {


		}
	}

	private function setNumberOfPages() {

		if((int) $this->entriesPerPage) {
			$this->numberOfPages = floor((int) $this->totalEntries / (int) $this->entriesPerPage) + 1;
		}

	}

	private function getCurrentOffset() {

		// @todo proper accessing GET parameters

		if(isset($GLOBALS['config']->_get[$this->parameterName])) {
			$this->currentPage = (int) $GLOBALS['config']->_get[$this->parameterName];

			if(!$this->currentPage) {
				$this->currentPage = 1;
			}
		}
		else {
			$this->currentPage = 1;
		}

	}

	private function initializeUrl() {

		// @todo proper accessing GET parameters

		$get = $GLOBALS['config']->_get;
		unset($get[$parameterName]);
		$urlFrag = http_build_query($get);

		$this->urlFragment = $this->config->getDocument(). '? ' . $urlFrag . (empty($urlFrag) ? '?' : '&') . $this->parameterName . '=';

	}
}

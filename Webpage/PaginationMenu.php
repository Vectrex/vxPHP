<?php
namespace vxPHP\Webpage;

class PaginationMenu {

	private	$parameterName,
			$entriesPerPage,
			$totalEntries,
			$numberOfPages,
			$template,
			$range,
			$document,
			$getParameters = array();

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

	}

	private function setNumberOfPages() {
		if((int) $this->entriesPerPage) {
			$this->numberOfPages = floor((int) $this->totalEntries / (int) $this->entriesPerPage) + 1;
		}
	}

	private function initializeUrl($url) {

	}
}

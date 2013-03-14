<?php
namespace vxPHP\Template\Parser;

abstract class TemplateParser implements ParserInterface {
	private $source;

	public function setSource($source) {
		$this->source = $source;
	}

	abstract public function parse();
}

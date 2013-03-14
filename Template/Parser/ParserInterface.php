<?php
namespace vxPHP\Template\Parser;

interface ParserInterface {

	private $source;

	public function setSource($source);
	public function parse();
}

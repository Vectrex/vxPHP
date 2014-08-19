<?php
namespace vxPHP\Template\Parser;

use vxPHP\Template\Parser\TemplateParser;

class HtmlTemplateParser extends TemplateParser {

	public function parse() {
		return $this->source;
	}
}

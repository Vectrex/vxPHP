<?php
class TemplateParser {
	private static $parserInstances = array();

	protected $source;

	protected function __construct() { }
	
	private function __clone() {}	

	public static function getParser($type = 'html') {
		if(!isset(self::$parserInstances[$type])) {
			$class = ucfirst($type).'TemplateParser';
			self::$parserInstances[$type] = new $class;
		}
		return self::$parserInstances[$type];
	}
}

interface templateSpecifics {
	public function setSource($source);
	public function parse();
}

class HtmlTemplateParser extends TemplateParser implements templateSpecifics {
	public function setSource($source) {
		$this->source = $source;
	}
	public function parse() {
		return $this->source;
	}
}

class MarkdownTemplateParser extends TemplateParser implements templateSpecifics {
	public function setSource($source) {
		$this->source = $source;
	}
	public function parse() {
		return Markdown($this->source);
	}
} 
?>
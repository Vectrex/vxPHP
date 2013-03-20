<?php
abstract class Plugin implements EventListener {
	public function __construct() {
	}
	
	/**
	 * reads configuration settings and turns them into local properties
	 * 
	 * @param SimpleXMLElement $configXML
	 */
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
	}
}
?>
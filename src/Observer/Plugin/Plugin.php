<?php
namespace vxPHP\Observer\Plugin;

use vxPHP\Observer\ListenerInterface;
use vxPHP\Observer\SubjectInterface;
use vxPHP\Observer\EventDispatcher;

abstract class Plugin implements ListenerInterface {
	public function __construct() {
	}

	/**
	 * reads configuration settings and turns them into local properties
	 *
	 * @param SimpleXMLElement $configXML
	 */
	public function configure(\SimpleXMLElement $configXML) {
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

	public function update(SubjectInterface $subject) {
		$eventType = EventDispatcher::getInstance()->getEventType();
		echo __CLASS__." was notified by '$eventType'.";
	}
}
?>
<?php
class skeleton_plugin extends Plugin implements EventListener {

	public function __construct() {
	}

	public function update(Subject $subject) {
		$eventType = EventDispatcher::getInstance()->getEventType();
		echo __CLASS__." was notified by '$eventType'.";
	}
}
?>
<?php
class Dummy_plugin extends Plugin {

	public function notify(StdClass $subject, $eventType) {
		echo __CLASS__." was notified by '$eventType'.";
	}
}
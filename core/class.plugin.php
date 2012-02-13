<?php
abstract class Plugin {
	public function __construct() {
	}

	public abstract function notify(StdClass $subject, $eventType) {
	}
}
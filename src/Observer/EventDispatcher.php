<?php

namespace vxPHP\Observer;

/**
 * simple dispatcher-listener implementation
 *
 * @version 0.0.3 2012-02-15
 * @author Gregor Kofler
 *
 */
class EventDispatcher {

	private $listeners = array(),
			$lastEvent,
			$lastSubject;

	private static $instance;

	private function __construct() {
	}
	private function __clone() {
	}

	public static function getInstance() {
		if(is_null(self::$instance)) {
			self::$instance = new EventDispatcher();
		}
		return self::$instance;
	}

	public function attach(ListenerInterface $listener, $eventType) {
		if(!isset($this->listeners[spl_object_hash($listener)])) {
			$this->listeners[spl_object_hash($listener)] = array();
			$this->listeners[spl_object_hash($listener)]['__instance__'] = $listener;
		}
		$this->listeners[spl_object_hash($listener)][$eventType] = TRUE;
	}

	public function detach(ListenerInterface $listener, $eventType) {
		unset($this->listeners[spl_object_hash($listener)][$eventType]);
	}

	public function notify(SubjectInterface $subject, $eventType) {
		$this->lastEvent = $eventType;
		$this->lastSubject = $subject;

		foreach($this->listeners as $listener) {
			if(isset($listener[$eventType])) {
				$listener['__instance__']->update($subject);
			}
		}
	}

	public function getEventType() {
		return $this->lastEvent;
	}

	public function getSubject() {
		return $this->lastSubject;
	}
}
?>
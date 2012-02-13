<?php
/**
 * simple dispatcher-listener implementation
 * 
 * @version 0.0.1 2012-02-12
 * @author Gregor Kofler
 *
 */
class EventDispatcher {

	private static $listeners = array();
	
	private function __construct() {
	}

	public static function attach(EventListener $listener, $eventType) {
		if(isset(self::$listeners[spl_object_hash($listener)])) {
			self::$listeners[spl_object_hash($listener)] = array();
		}
		self::$listeners[spl_object_hash($listener)][$eventType] = TRUE;
	}

	public static function detach(EventListener $listener, $eventType) {
		unset(self::$listeners[spl_object_hash($listener)][$eventType]);
		if(!count(self::$listeners[spl_object_hash($listener)])) {
			unset(self::$listeners[spl_object_hash($listener)]);
		}
	}

	public static function notify(stdClass $subject, $eventType) {
		foreach(self::$listeners as $listener) {
			if(isset($listener[$eventType])) {
				$listener->update($subject);
			}
		}
	}

}

interface EventListener {
	public function update();
}
?>
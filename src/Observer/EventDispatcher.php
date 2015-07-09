<?php

namespace vxPHP\Observer;

/**
 * simple dispatcher-listener implementation
 *
 * @version 0.1.0 2015-07-09
 * @author Gregor Kofler
 *
 */
class EventDispatcher {

	/**
	 * @var array
	 */
	private $listeners = array();

	/**
	 * type of last event
	 * @var string
	 */
	private	$lastEvent;

	/**
	 * subject serving last event
	 * @var SubjectInterface
	 */
	private	$lastSubject;

	/**
	 * singleton pattern
	 * @var EventDispatcher
	 */
	private static $instance;

	private function __construct() {
	}

	private function __clone() {
	}

	/**
	 * get dispatcher instance
	 * @return EventDispatcher
	 */
	public static function getInstance() {

		if(is_null(self::$instance)) {
			self::$instance = new EventDispatcher();
		}

		return self::$instance;

	}

	/**
	 * register a listener for a certain event type
	 *  
	 * @param ListenerInterface $listener
	 * @param string $eventType
	 */
	public function attach(ListenerInterface $listener, $eventType) {

		if(!isset($this->listeners[spl_object_hash($listener)])) {
			$this->listeners[spl_object_hash($listener)] = array();
			$this->listeners[spl_object_hash($listener)]['__instance__'] = $listener;
		}

		$this->listeners[spl_object_hash($listener)][$eventType] = TRUE;

	}

	/**
	 * unregister a listener for a certain event type
	 * 
	 * @param ListenerInterface $listener
	 * @param string $eventType
	 */
	public function detach(ListenerInterface $listener, $eventType = NULL) {

		if(is_null($eventType)) {
			unset($this->listeners[spl_object_hash($listener)]);
		}
		else {
			unset($this->listeners[spl_object_hash($listener)][$eventType]);
		}

	}

	/**
	 * receive an event served by $subject and call inform all subscribing listeners
	 * 
	 * @param SubjectInterface $subject
	 * @param string $eventType
	 */
	public function notify(SubjectInterface $subject, $eventType) {

		$this->lastEvent = $eventType;
		$this->lastSubject = $subject;

		foreach($this->listeners as $listener) {

			if(isset($listener[$eventType])) {
				$listener['__instance__']->update($subject);
			}

		}

	}

	/**
	 * provide access to last served event type
	 * @return string
	 */
	public function getEventType() {
		return $this->lastEvent;
	}

	/**
	 * provide access to subject which triggered last event
	 * @return string
	 */
	public function getSubject() {

		return $this->lastSubject;

	}
}

<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Observer;

/**
 * simple dispatcher-listener implementation
 *
 * @version 0.3.0 2015-12-12
 * @author Gregor Kofler
 *
 */
class EventDispatcher {

	/**
	 * lookup for all registered subscribers
	 * eventName -> priority -> callable
	 * @var array
	 */
	private $registry = array();

	/**
	 * lookup for registered subscribers sorted by priority
	 * eventName -> callable
	 * @var array
	 */
	private $sortedRegistry = array();

	/**
	 * register object hashes
	 * an object is served only once
	 * 
	 * @var array
	 */
	private $registeredHashes = array();

	/**
	 * last event which was served
	 * wraps emitting element
	 * 
	 * @var Event
	 */
	private	$lastEvent;

	/**
	 * singleton pattern
	 * @var EventDispatcher
	 */
	private static $instance;

	/**
	 * ensure singleton
	 */
	private function __construct() {
	}

	/**
	 * ensure singleton
	 */
	private function __clone() {
	}

	/**
	 * get dispatcher instance
	 * 
	 * @return EventDispatcher
	 */
	public static function getInstance() {

		if(is_null(self::$instance)) {
			self::$instance = new static();
		}

		return self::$instance;

	}

	/**
	 * remove a subscriber from the registry
	 * 
	 * @param SubscriberInterface $instance
	 */
	public function removeSubscriber(SubscriberInterface $instance) {

		// check for object hash

		if(FALSE !== ($pos = array_search(spl_object_hash($instance), $this->registeredHashes))) {
		
			// remove instance from registry before re-inserting
			
			unset($this->registeredHashes[$pos]);

			foreach($instance::getEventsToSubscribe() as $eventName => $parameters) {
			
				$methodName	= array_shift($parameters);
				$callable	= array($instance, $methodName);
			
				foreach($this->registry[$eventName] as $priority => $subscribers) {
						
					if (FALSE !== ($pos = array_search($callable, $subscribers, TRUE))) {
						unset(
							$this->registry[$eventName][$priority][$pos],
							$this->sortedRegistry[$eventName]
						);
					}

				}
			}
		}
	}
	
	/**
	 * register a subscriber for event types provided by subscriber
	 * 
	 * @param SubscriberInterface $instance
	 */
	public function addSubscriber(SubscriberInterface $instance) {

		// make sure that an already registered object is removed before re-registering
		
		$this->removeSubscriber($instance);

		// register object hash

		$this->registeredHashes[] = spl_object_hash($instance);

		// parameters contain at least a method name; additionally a priority can be added
		
		foreach($instance::getEventsToSubscribe() as $eventName => $parameters) {

			$parameters	= (array) $parameters;
			$methodName	= array_shift($parameters); 

			if(count($parameters)) {
				$priority = (int) $parameters[0];
			}
			else {
				$priority = 0;
			}

			if(!isset($this->registry[$eventName]) || !isset($this->registry[$eventName][$priority])) {
				$this->registry[$eventName][$priority] = array();
			}

			$this->registry[$eventName][$priority][]	= array($instance, $methodName);

			// force re-sort upon next dispatch

			unset ($this->sortedRegistry[$eventName]);

		}
		
	}

	/**
	 * receive an event served by $subject and call inform all subscribing listeners
	 * 
	 * @param Event $event
	 */
	public function dispatch(Event $event) {

		$this->lastEvent	= $event;
		$eventName			= $event->getName();

		if (isset($this->registry[$eventName])) {

			
			foreach ($this->getSortedRegistry($eventName) as $listener) {
				call_user_func($listener, $event);
			}
			
		}

	}

	/**
	 * provide access to last event which was triggered
	 * @return string
	 */
	public function getLastEvent() {

		return $this->lastEvent;

	}
	
	/**
	 * helper method to collect all listener callbacks for a named event
	 * into one array observing priorities
	 * 
	 * @param string $eventName
	 */
	private function getSortedRegistry($eventName) {
		
		if(!isset($this->sortedRegistry[$eventName])) {
			
			$this->sortedRegistry[$eventName] = array();
			
			if (isset($this->registry[$eventName])) {
				
				// sort reverse by priority key

				krsort($this->registry[$eventName]);
				
				// merge the sorted arrays into one

				$this->sortedRegistry[$eventName] = call_user_func_array('array_merge', $this->registry[$eventName]);
			}
		}

		return $this->sortedRegistry[$eventName];

	}
}

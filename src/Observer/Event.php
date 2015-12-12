<?php

namespace vxPHP\Observer;

/**
 * the Event instance wraps both the
 * object which served the event and additional data
 * 
 * @version 0.1.0 2015-12-12
 * @author Gregor Kofler
 */
abstract class Event {

	/**
	 * name of event
	 * @var string
	 */
	private	$name;
	
	/**
	 * instance which publishes event
	 * @param PublisherInterface
	 */
	private $publisher;

	public function __construct($eventName, PublisherInterface $publisher) {

		$this->name			= $eventName;
		$this->publisher	= $publisher;

	}
	
	/**
	 * return name of event
	 * 
	 * @return string
	 */
	public function __toString() {

		return $this->name;

	}

	/**
	 * get name of event
	 * 
	 * @return string
	 */
	public function getName() {

		return $this->name;

	}
	
	/**
	 * get instance of event publisher
	 *
	 * @return PublisherInterface
	 */
	public function getPublisher() {
	
		return $this->publisher;
	
	}

	/**
	 * trigger event by invoking the EventDispatcher
	 */
	public function trigger() {

		EventDispatcher::getInstance()->dispatch($this);		

	}
	
	/**
	 * static method for fluent API
	 * 
	 * @param string $eventName
	 * @param PublisherInterface $publisher
	 * @return static
	 * 
	 */
	public static function create($eventName, PublisherInterface $publisher) {

		return new static($eventName, $publisher);

	}
}
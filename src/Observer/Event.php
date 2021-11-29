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
 * the Event instance wraps both the
 * object which served the event and additional data
 * 
 * @version 0.1.1 2021-12-01
 * @author Gregor Kofler
 */
abstract class Event
{
	/**
	 * name of event
	 * @var string
	 */
	private	string $name;
	
	/**
	 * instance which publishes event
	 * @param PublisherInterface
	 */
	private PublisherInterface $publisher;

	public function __construct($eventName, PublisherInterface $publisher)
    {
		$this->name = $eventName;
		$this->publisher = $publisher;
	}
	
	/**
	 * return name of event
	 * 
	 * @return string
	 */
	public function __toString()
    {
		return $this->name;
	}

	/**
	 * get name of event
	 * 
	 * @return string
	 */
	public function getName(): string
    {
		return $this->name;
	}
	
	/**
	 * get instance of event publisher
	 *
	 * @return PublisherInterface
	 */
	public function getPublisher(): PublisherInterface
    {
		return $this->publisher;
	}

	/**
	 * trigger event by invoking the EventDispatcher
	 */
	public function trigger(): void
    {
		EventDispatcher::getInstance()->dispatch($this);
	}

    /**
     * static method for fluent API
     *
     * @param string $eventName
     * @param PublisherInterface $publisher
     * @return static
     */
	public static function create(string $eventName, PublisherInterface $publisher)
    {
		return new static($eventName, $publisher);
	}
}
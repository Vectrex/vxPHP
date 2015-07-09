<?php
namespace vxPHP\Observer;

use vxPHP\Observer\ListenerInterface;
use vxPHP\Observer\SubjectInterface;
use vxPHP\Observer\EventDispatcher;

/**
 * abstract class to allow simple dependency injection
 * with a simple observer pattern
 *
 * @author Gregor Kofler
 * @version 0.1.0 2015-07-09
 *
 */
abstract class Plugin implements ListenerInterface {

	/**
	 * @var array
	 */
	protected $parameters;

	public function setParameters(array $parameters = NULL) {
	
		$this->parameters = $parameters;
	
	}

	public function update(SubjectInterface $subject) {

		$eventType = EventDispatcher::getInstance()->getEventType();
		echo sprintf("'%s' was notified by '%s'.", __CLASS__, $eventType);

	}
}

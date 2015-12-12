<?php

namespace vxPHP\Observer;

use vxPHP\Observer\SubscriberInterface;

class GenericSubscriber implements SubscriberInterface {
	
	public function __construct() {
		
	}

	public function listen(Event $event) {
		
		if($event->getName() === GenericEvent::TRIGGERED) {
			
			echo sprintf("Listener for '%s' called.", GenericEvent::TRIGGERED);
			
		}
		
	}
	
	/**
	 * {@inheritDoc}
	 * @see \vxPHP\Observer\SubscriberInterface::getEventsToSubscribe()
	 */
	public static function getEventsToSubscribe() {
		
		return array(
			GenericEvent::TRIGGERED => 'listen'
		);

	}
	
}
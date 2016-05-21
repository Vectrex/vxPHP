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
<?php

namespace vxPHP\Observer;

use vxPHP\Observer\Event;

class GenericEvent extends Event {
	
	const TRIGGERED = 'GenericEvent.triggered';
	
	public function __construct($eventName, PublisherInterface $publisher) {

		// optional event type specific stuff happens here
		
		parent::__construct ($eventName, $publisher);

	}
}
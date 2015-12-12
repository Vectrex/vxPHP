<?php

namespace vxPHP\Observer;

use vxPHP\Observer\PublisherInterface;

class GenericPublisher implements PublisherInterface {
	
	public function __construct() {
		
	}
	
	public function triggerEvent() {
		
		GenericEvent::create(GenericEvent::TRIGGERED, $this)->trigger();

	}
	
}
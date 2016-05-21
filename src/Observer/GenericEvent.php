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

use vxPHP\Observer\Event;

class GenericEvent extends Event {
	
	const TRIGGERED = 'GenericEvent.triggered';
	
	public function __construct($eventName, PublisherInterface $publisher) {

		// optional event type specific stuff happens here
		
		parent::__construct ($eventName, $publisher);

	}
}
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

class GenericEvent extends Event
{
	public const string TRIGGERED = 'GenericEvent.triggered';
	
	public function __construct (string $eventName, PublisherInterface $publisher)
    {
		// optional event type specific stuff happens here
		
		parent::__construct ($eventName, $publisher);
	}
}
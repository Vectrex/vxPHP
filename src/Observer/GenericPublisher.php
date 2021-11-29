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

class GenericPublisher implements PublisherInterface
{
	public function __construct() {}
	
	public function triggerEvent(): void
    {
		GenericEvent::create(GenericEvent::TRIGGERED, $this)->trigger();
	}
	
}
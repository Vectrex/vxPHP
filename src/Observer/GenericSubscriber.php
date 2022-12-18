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

class GenericSubscriber implements SubscriberInterface
{
	public function __construct() {}

	public function listen(Event $event): void
    {
		if($event->getName() === GenericEvent::TRIGGERED) {
			echo sprintf("Listener for '%s' called.", GenericEvent::TRIGGERED);
		}
	}
	
	/**
	 * {@inheritDoc}
	 * @see \vxPHP\Observer\SubscriberInterface::getEventsToSubscribe()
	 */
	public static function getEventsToSubscribe(): array
    {
		return [
			GenericEvent::TRIGGERED => 'listen'
        ];
	}
}
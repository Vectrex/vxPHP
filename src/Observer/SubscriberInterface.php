<?php
namespace vxPHP\Observer;

interface SubscriberInterface {

	/**
	 * returns array with event names as keys and
	 * method name and optional priority as values
	 * 
	 * @return array
	 */
	public static function getEventsToSubscribe();

}

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

interface SubscriberInterface {

	/**
	 * returns array with event names as keys and
	 * method name and optional priority as values
	 * 
	 * @return array
	 */
	public static function getEventsToSubscribe();

}

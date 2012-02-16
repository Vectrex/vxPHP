<?php
/**
 * stub for more detailed interfaces in the future
 * @author Gregor Kofler
 */

/**
 * EventDispatcher related interfaces
 */
interface EventListener {
	public function update(Subject $subject);
}

interface Subject {
}

/**
 * webpage related interfaces
 */
interface siteSpecifics {
	public function pageHeader();
	public function pageFooter();
	public function content();
}

/**
 * template parser related interfaces
 */
interface templateSpecifics {
	public function setSource($source);
	public function parse();
}
?>
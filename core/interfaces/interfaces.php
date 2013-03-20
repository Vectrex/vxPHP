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

/**
 * mailer related interfaces
 */
interface Mailer {
	public function connect();
	public function close();
	public function setCredentials($user, $pwd);
	public function setFrom($from);
	public function setTo($to);
	public function setHeaders(array $headers);
	public function setMessage($message);
	public function send();
}
?>
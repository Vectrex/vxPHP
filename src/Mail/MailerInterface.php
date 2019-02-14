<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Mail;

interface MailerInterface {
	public function __construct($host, $port, $encryption);
	public function connect();
	public function close();
	public function setCredentials($user, $pwd, $authMethod = null);
	public function setFrom($from);
	public function setTo($to);
	public function setHeaders(array $headers);
	public function setMessage($message);
	public function send();
}

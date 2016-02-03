<?php

namespace vxPHP\Mail;

interface MailerInterface {
	public function __construct($host, $port, $encryption);
	public function connect();
	public function close();
	public function setCredentials($user, $pwd);
	public function setFrom($from);
	public function setTo($to);
	public function setHeaders(array $headers);
	public function setMessage($message);
	public function send();
}

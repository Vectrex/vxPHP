<?php

namespace vxPHP\Mail;

use vxPHP\Mail\Exception\SmtpMailerException;

/**
 * simple SMTP mailer
 *
 * @author Gregor Kofler
 * @version 0.1.4 2013-02-07
 *
 */
class SmtpMailer implements MailerInterface {

	const CRLF = "\r\n";

	const RFC_SERVICE_READY		= 220;
	const RFC_SERVICE_CLOSING	= 221;
	const RFC_AUTH_SUCCESSFUL	= 235;
	const RFC_REQUEST_OK		= 250;
	const RFC_CONTINUE_REQUEST	= 334;
	const RFC_START_MAIL_INPUT	= 354;
	
	private	$host,
			$port,
			$user,
			$pwd,
			$type,
			$timeout,
			$from = '',
			$to,
			$headers = array(),
			$message,
			$authTypes = array('NONE', 'LOGIN', 'PLAIN', 'CRAM-MD5'),

			$socket,
			$response,
			$log = array();

	/**
	 * @param string $host
	 * @param integer $port
	 */
	public function __construct($host, $port = 25) {
		$this->host	= $host;
		$this->port	= $port;
	}

	/**
	 * establishes connection with SMTP server
	 * 
	 * @param integer $timeout
	 * 
	 * @throws SmtpMailerException
	 */
	public function connect($timeout = 10) {
		$this->timeout = $timeout;

		$this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

		if (!$this->socket OR !$this->check(self::RFC_SERVICE_READY)) {
			throw new SmtpMailerException("Connection failed. $errno: $errstr", SmtpMailerException::CONNECTION_FAILED);
		}

//		stream_set_blocking($this->socket, 1);
		stream_set_timeout($this->socket, $this->timeout, 0);
	}

	/**
	 * close connection
	 */
	public function close() {
		if($this->socket) {
			fclose($this->socket);
		}
	}

	/**
	 * set authentication data
	 * 
	 * @param string $user
	 * @param string $pwd
	 * @param string $type
	 * 
	 * @throws SmtpMailerException
	 */
	public function setCredentials($user, $pwd, $type = 'LOGIN') {
		$this->user = $user;
		$this->pwd = $pwd;
		if(!in_array(strtoupper($type), $this->authTypes)) {
			throw new SmtpMailerException("Invalid authentication type '$type'.", SmtpMailerException::INVALID_AUTH_TYPE);
		}
		$this->type = strtoupper($type);
	}

	/**
	 * sets addressor
	 *
	 * @param string $from
	 */
	public function setFrom($from) {
		$this->from = trim($from);
		if(!preg_match('~.*?<.*?>$~', $this->from)) {
			$this->from = '<'.$this->from.'>';
		}
	}
	
	/**
	 * sets receiver
	 *
	 * @param mixed $to
	 */
	public function setTo($to) {
		if(!is_array($to)) {
			$to = (array) $to;
		}
		$this->to = array();
		
		foreach($to as $receiver) { 
			$receiver = trim($receiver);

			if(!preg_match('~.*?<.*?>$~', $receiver)) {
				$this->to[] = "<$receiver>";
			}
			else {
				$this->to[] = $receiver;
			}
		}
	}

	/**
	 * set additional headers with associative array
	 * 
	 * @param array $headers
	 */
	public function setHeaders(array $headers) {
		$this->headers = $headers;
	}

	/**
	 * set mail body
	 * 
	 * @param string $message
	 */
	public function setMessage($message) {
		$this->message = $message;
	}
	
	/**
	 * sends ehlo, authentication, from, to headers, message and quit
	 */
	public function send() {

		$this->sendEhlo();
		$this->auth();

		$this->put('MAIL FROM:'.$this->from.self::CRLF);
		
		if(!$this->check(self::RFC_REQUEST_OK)) {
			throw new SmtpMailerException('Failed to send addressor.', SmtpMailerException::ADDRESSOR_SEND_FAILED);
		}
		
		if(empty($this->to)) {
			throw new SmtpMailerException('Failed to send recipient.', SmtpMailerException::RCPT_SEND_FAILED);
		}
		foreach ($this->to as $receiver) {
			$this->put('RCPT TO:'.$receiver.self::CRLF);
			
			if(!$this->check(self::RFC_REQUEST_OK)) {
				throw new SmtpMailerException("Failed to send recipient $receiver.", SmtpMailerException::RCPT_SEND_FAILED);
			}
		}

		$this->put('DATA'.self::CRLF);

		if (!$this->check(self::RFC_START_MAIL_INPUT)) {
			throw new SmtpMailerException('Data-transfer failed.', SmtpMailerException::DATA_TRANSFER_FAILED);
		}

		$payload = array();

		foreach(array_keys($this->headers) as $k) {

			// remove an optional bcc header

			if(strtolower($k) !== 'bcc') {
				$payload[] = "$k: {$this->headers[$k]}";
			}
		}

		if(count($payload)) {
			$payload[] = '';
		}

		array_push(
			$payload,
			$this->message,
			'',
			'',
			'.',
			''
		);

		$this->put(implode(self::CRLF, $payload));
		
		// force log entry

		$this->getResponse();

		$this->put('QUIT'.self::CRLF);
		
		// force log entry
		
		$this->getResponse();
	}	

	/**
	 * sends enhanced helo
	 */
	private function sendEhlo() {
		$this->put("EHLO ". $this->host . self::CRLF);

		if (!$this->check(self::RFC_REQUEST_OK)) {
			throw new SmtpMailerException('Failed to send EHLO.', SmtpMailerException::EHLO_FAILED);
		}
	}

	/**
	 * sends helo
	 */
	private function sendHelo() {
		$this->put("HELO ". $this->host . self::CRLF);

		if (!$this->check(self::RFC_REQUEST_OK)) {
			throw new SmtpMailerException('Failed to send HELO.', SmtpMailerException::HELO_FAILED);
		}
	}

	/**
	 * send credentials to server
	 * 
	 * @throws SmtpMailerException
	 */
	private function auth() {

		if($this->type == 'NONE') {
			return;
		}

		$this->put("AUTH ".$this->type.self::CRLF);
		$this->getResponse();

		if( substr($this->response, 0, 1) != '3') {
			throw new SmtpMailerException('Failed to send AUTH.', SmtpMailerException::AUTH_SEND_FAILED);
		}
	
		if ($this->type == 'LOGIN') {
			$this->put(base64_encode($this->user).self::CRLF);

			if(!$this->check(self::RFC_CONTINUE_REQUEST)) {
				throw new SmtpMailerException('Failed to send username.', SmtpMailerException::USERNAME_SEND_FAILED);
			}
	
			$this->put(base64_encode($this->pwd).self::CRLF);
		}

		elseif ($this->type == 'PLAIN') {
			$this->put(base64_encode($this->user.chr(0).$this->user.chr(0).$this->pwd).self::CRLF);
		}

		elseif ($this->type == 'CRAM-MD5') {

			$data	= explode(' ',$this->response);
			$data	= base64_decode($data[1]);
			$key	= str_pad($this->pwd, 64, chr(0x00));
			$ipad	= str_repeat(chr(0x36), 64);
			$opad	= str_repeat(chr(0x5c), 64);

			$this->put(base64_encode($this->user.' '.md5(($key ^ $opad).md5(($key ^ $ipad).$data, TRUE))).self::CRLF);
		}

		if(!$this->check(self::RFC_AUTH_SUCCESSFUL)) {
			throw new SmtpMailerException('Authentication failed.', SmtpMailerException::AUTH_FAILED);
		}
	}

	/**
	 * get logged entries
	 *
	 * @return array
	 */
	public function getLog() {
		return $this->log;
	}

	/**
	 * send command
	 *
	 * @param String $cmd
	 */
	private function put($cmd) {
		fputs($this->socket, $cmd);
		$this->log($cmd);
	}

	/**
	 * retrieve response of server
	 */
	private function getResponse() {

		$message = '';
		$continue = TRUE;
		
		do {
			$tmp = fgets($this->socket, 1024);
			if($tmp === FALSE) {
				$continue = FALSE;
			}
			else {
				$message .= $tmp;
				//if(preg_match('~^[0-9]{3}~', $message)) {
				if(preg_match('~^([0-9]{3})(-(.*[\r\n]{1,2})+\\1)? [^\r\n]+[\r\n]{1,2}$~', $message)) {
					$continue = FALSE;
				}
			}
		} while($continue);

		$this->response = $message;
		$this->log($message);
	}

	/**
	 * checks if response code is correct
	 *
	 * @param String $code
	 * @return Boolean
	 */
	private function check($code) {
		$this->getResponse();
		return !empty($this->response) && preg_match("~^$code~", $this->response);
	}

	/**
	 * collect log entries
	 */
	private function log($str) {
		$this->log[] = $str;
	}
}

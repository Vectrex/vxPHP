<?php

namespace vxPHP\Mail;

use vxPHP\Mail\Exception\SmtpMailerException;

/**
 * simple SMTP mailer
 *
 * @author Gregor Kofler
 * @version 0.3.0 2016-02-03
 *
 * validity of email addresses is not checked
 * only encoding where necessary is applied
 */
class SmtpMailer implements MailerInterface {

	const CRLF = "\r\n";
	const RFC5322_ATOM_CHARS	= "!#$%&'*+-/=?^_`{|}~a-zA-Z0-9";
	
	const RFC_SERVICE_READY		= 220;
	const RFC_SERVICE_CLOSING	= 221;
	const RFC_AUTH_SUCCESSFUL	= 235;
	const RFC_REQUEST_OK		= 250;
	const RFC_CONTINUE_REQUEST	= 334;
	const RFC_START_MAIL_INPUT	= 354;

	const DEFAULT_PORT			= 25;
	
			/**
			 * preferences for MIME encoding
			 * used with mb_internal_encoding()
			 * mb_encode_mimeheader()
			 * @var array
			 */
	private	$mimeEncodingPreferences = array(
				'scheme'			=> 'Q',
				'input-charset'		=> 'UTF-8',
				'output-charset'	=> 'UTF-8',
				'line-break-chars'	=> self::CRLF
			);

			/**
			 * host address of SMTP server
			 * @var string
			 */
	private	$host;
	
			/**
			 * port of SMTP server
			 * @var integer
			 */
	private	$port;

			/**
			 * username for authentication
			 * @var string
			 */
	private	$user;

			/**
			 * password for authentication
			 * @var string
			 */
	private	$pwd;

			/**
			 * auth method used for SMTP authentication
			 * @var string
			 */
	private	$authType;
	
			/**
			 * supported auth methods
			 * @var array
			 */
	private	$authTypes = array('NONE', 'LOGIN', 'PLAIN', 'CRAM-MD5');

	
			/**
			 * encryption method used for connection
			 * @var string
			 */
	private $smtpEncryption;

			/**
			 * supported encryption methods
			 * @var array
			 */
	private	$smtpEncryptions = array('SSL', 'TLS');

			/**
			 * connection timeout
			 * @var integer
			 */
	private	$timeout;
	
			/**
			 * connection socket
			 * @var resource
			 */
	private	$socket;

			/**
			 * email address of sender
			 * @var string
			 */
	private	$from = '';
	
			/**
			 * extracted display name, when from address is of form "display_name" <email_from>
			 * @var string
			 */
	private	$fromDisplayName = '';

			/**
			 * holds receivers with email addresses
			 * @var array
			 */
	private	$to;
	
			/**
			 * header rows
			 * @var array
			 */
	private	$headers = array();
	
			/**
			 * the mail message
			 * @var string
			 */
	private	$message;

			/**
			 * server response
			 * @var string
			 */
	private	$response;
	
			/**
			 * server communication log
			 * @var log
			 */
	private	$log = array();

	/**
	 * constructor
	 * configures only basic server settings
	 * and default mime encoding
	 * 
	 * @param string $host
	 * @param integer $port
	 * @param string $smtpEncryption
	 */
	public function __construct($host, $port = NULL, $smtpEncryption = NULL) {

		if($smtpEncryption) {

			if(!in_array(strtoupper($smtpEncryption), $this->smtpEncryptions)) {
				throw new SmtpMailerException(sprintf("Invalid encryption type '%s'.", $smtpEncryption), SmtpMailerException::INVALID_ENCRYPTION_TYPE);
			}

			$this->smtpEncryption = strtolower($smtpEncryption);

		}

		$this->port = is_null($port) ? self::DEFAULT_PORT : $port;
		$this->host	= $host;
		
		mb_internal_encoding($this->mimeEncodingPreferences['output-charset']);

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
		
		if($this->smtpEncryption === 'ssl') {
			$protocol = $this->smtpEncryption . '://';
		}
		else {
			$protocol = '';
		}

		if($this->smtpEncryption === 'tls' && !function_exists('stream_socket_enable_crypto')) {
			throw new SmtpMailerException('TLS encryption not possible: stream_socket_enable_crypto() not found.', SmtpMailerException::TLS_FAILED);
		}

		$this->socket = @fsockopen($protocol . $this->host, $this->port, $errno, $errstr, $this->timeout);

		if (!$this->socket OR !$this->check(self::RFC_SERVICE_READY)) {
			throw new SmtpMailerException(sprintf('Connection failed. %d: %s.', $errno, $errstr), SmtpMailerException::CONNECTION_FAILED);
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
	 * @param string $authType
	 * 
	 * @throws SmtpMailerException
	 */
	public function setCredentials($user, $pwd, $authType = 'LOGIN') {

		$this->user = $user;
		$this->pwd = $pwd;

		if(!in_array(strtoupper($authType), $this->authTypes)) {
			throw new SmtpMailerException(sprintf("Invalid authentication type '%s'.", $authType), SmtpMailerException::INVALID_AUTH_TYPE);
		}

		$this->authType = strtoupper($authType);

	}

	/**
	 * sets addressor
	 *
	 * @param string $from
	 */
	public function setFrom($from) {

		$this->from = trim($from);

		$this->fromDisplayName = '';

		if(preg_match('~^(.*?)\s*<(.*?)>$~', $this->from, $matches)) {
			
			$this->from = trim($matches[2]);

			if(trim($matches[1])) {
				$this->fromDisplayName = $matches[1];
			}

		}
		
		// encode email, when non-atom chars + "@" + "." are found in email
		if(!preg_match('/^[' . preg_quote(self::RFC5322_ATOM_CHARS, '/') . '@.]+$/', $this->from)) {
			$this->from = mb_encode_mimeheader($this->from, mb_internal_encoding(), $this->mimeEncodingPreferences['scheme']);
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

			// extract emails

			if(preg_match('~^(.*?)\s*<(.*?)>$~', $receiver, $matches)) {
				$email = trim($matches[2]);
			}
			
			else {
				$email = $receiver;
			}

			// remove duplicates

			if(!in_array($email, $this->to)) {
				$this->to[] = $email;
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

		// EHLO/HELO

		$ehlo = TRUE;

		try {
			$this->sendEhlo();
		}

		catch(SmtpMailerException $e) {
			if($e->getCode() !== SmtpMailerException::EHLO_FAILED) {
				throw $e;
			}

			$ehlo = FALSE;
			$this->sendHelo();
		}
		
		// optional TLS

		if($this->smtpEncryption === 'tls') {
			$this->startTLS();
			
			// re-send ehlo/helo
			
			if($ehlo) {
				$this->sendEhlo();
			}
			else {
				$this->sendHelo();
			}
		}

		// authentication

		$this->auth();

		// header fields

		$this->put('MAIL FROM:<' . $this->from . '>' . self::CRLF);
		
		if(!$this->check(self::RFC_REQUEST_OK)) {
			throw new SmtpMailerException('Failed to send addressor.', SmtpMailerException::ADDRESSOR_SEND_FAILED);
		}
		
		if(empty($this->to)) {
			throw new SmtpMailerException('Failed to send recipient.', SmtpMailerException::RCPT_SEND_FAILED);
		}

		foreach ($this->to as $receiver) {
			$this->put('RCPT TO:<' . $receiver . '>' . self::CRLF);
			
			if(!$this->check(self::RFC_REQUEST_OK)) {
				throw new SmtpMailerException(sprintf("Failed to send recipient '%s'.", $receiver), SmtpMailerException::RCPT_SEND_FAILED);
			}
		}

		$this->put('DATA' . self::CRLF);

		if (!$this->check(self::RFC_START_MAIL_INPUT)) {
			throw new SmtpMailerException('Data-transfer failed.', SmtpMailerException::DATA_TRANSFER_FAILED);
		}

		$payload = $this->buildHeaderRows();

		// insert empty row

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
	 * build the complete set of header rows
	 * 
	 * @return array
	 */
	private function buildHeaderRows() {
		
		$rows = array();
		
		foreach(array_keys($this->headers) as $k) {
		
			switch(strtolower($k)) {
		
				// remove an optional bcc header
		
				case 'bcc':
					break;
		
				case 'subject':
					$rows[] = iconv_mime_encode($k, $this->headers[$k], $this->mimeEncodingPreferences);
					break;
		
				case 'cc':
				case 'to':
					$addresses = preg_split('/\s*,\s*/', $this->headers[$k]);
					
					$encodedAddresses = array();

					foreach($addresses as $address) {

						$address = trim($address);
					
						// extract emails
					
						if(preg_match('~^(.*?)\s*<(.*?)>$~', $address, $matches)) {
							
							// encode display name
							
							$email			= trim($matches[2]);
							$displayName	= mb_encode_mimeheader(trim($matches[1]), mb_internal_encoding(), $this->mimeEncodingPreferences['scheme']);
						}
						else {
							$email			= $address;
							$displayName	= NULL;
						}

						// encode email, when non-atom chars + "@" + "." are found in email

						if(!preg_match('/^[' . preg_quote(self::RFC5322_ATOM_CHARS, '/') . '@.]+$/', $email)) {
							$email = mb_encode_mimeheader($email, mb_internal_encoding(), $this->mimeEncodingPreferences['scheme']);
						}
						
						if($displayName) {
							$encodedAddresses[] = sprintf('%s <%s>', $displayName, $email);
						}
						else {
							$encodedAddresses[] = $email;
						}
						
					}
					
					$rows[] = sprintf('%s: %s', $k, implode(', ', $encodedAddresses));
					break;

				case 'return-path':
				case 'reply-to':
					if(preg_match('~^(.*?)\s*<(.*?)>$~', $this->headers[$k], $matches)) {

						// encode display name

						$email			= trim($matches[2]);
						$displayName	= mb_encode_mimeheader(trim($matches[1]), mb_internal_encoding(), $this->mimeEncodingPreferences['scheme']);
					}
					else {
						$email			= trim($this->headers[$k]);
						$displayName	= NULL;
					}

					// encode email, when non-atom chars + "@" + "." are found in email
					
					if(!preg_match('/^[' . preg_quote(self::RFC5322_ATOM_CHARS, '/') . '@.]+$/', $email)) {
						$email = mb_encode_mimeheader($email, mb_internal_encoding(), $this->mimeEncodingPreferences['scheme']);
					}

					if($displayName) {
						$rows[] = sprintf('%s: %s <%s>', $k, $displayName, $email);
					}
					else {
						$rows[] = $k . ': ' . $email;
					}
					break;
						
				case 'from':
					if($this->fromDisplayName) {
						$rows[] = sprintf(
							'%s: %s <%s>',
							$k,
							mb_encode_mimeheader($this->fromDisplayName, mb_internal_encoding(), $this->mimeEncodingPreferences['scheme']),
							$this->from
						);
					}
					else {
						$rows[] = $k . ': ' . $this->from;
					}
					break;
		
				default:
					$rows[] = $k . ': ' . $this->headers[$k];
			}

		}

		return $rows;

	}

	
	/**
	 * sends enhanced helo
	 * 
	 * @throws SmtpMailerException
	 */
	private function sendEhlo() {
		$this->put("EHLO " . $this->host . self::CRLF);

		if (!$this->check(self::RFC_REQUEST_OK)) {
			throw new SmtpMailerException('Failed to send EHLO.', SmtpMailerException::EHLO_FAILED);
		}
	}

	/**
	 * sends helo
	 * 
	 * @throws SmtpMailerException
	 */
	private function sendHelo() {
		$this->put("HELO " . $this->host . self::CRLF);

		if (!$this->check(self::RFC_REQUEST_OK)) {
			throw new SmtpMailerException('Failed to send HELO.', SmtpMailerException::HELO_FAILED);
		}
	}

	/**
	 * initiate TLS (encrypted) session
	 * 
	 * @throws SmtpMailerException
	 */
	private function startTLS() {

	$this->put('STARTTLS' . self::CRLF);

		if (!$this->check(self::RFC_SERVICE_READY)) {
			throw new SmtpMailerException('Failed to establish TLS.', SmtpMailerException::TLS_FAILED);
		}
		
		if(
			!stream_socket_enable_crypto(
				$this->socket,
				TRUE,
				STREAM_CRYPTO_METHOD_TLS_CLIENT
			)
		) {
			throw new SmtpMailerException('TLS encryption of stream failed.', SmtpMailerException::TLS_FAILED);
		}

	}
	
	/**
	 * send credentials to server
	 * 
	 * @throws SmtpMailerException
	 */
	private function auth() {

		if($this->authType == 'NONE') {
			return;
		}

		$this->put("AUTH " . $this->authType . self::CRLF);
		$this->getResponse();

		if( substr($this->response, 0, 1) != '3') {
			throw new SmtpMailerException('Failed to send AUTH.', SmtpMailerException::AUTH_SEND_FAILED);
		}
	
		if ($this->authType == 'LOGIN') {
			$this->put(base64_encode($this->user) . self::CRLF);

			if(!$this->check(self::RFC_CONTINUE_REQUEST)) {
				throw new SmtpMailerException('Failed to send username.', SmtpMailerException::USERNAME_SEND_FAILED);
			}
	
			$this->put(base64_encode($this->pwd) . self::CRLF);
		}

		elseif ($this->authType == 'PLAIN') {
			$this->put(base64_encode($this->user . chr(0) . $this->user . chr(0) . $this->pwd) . self::CRLF);
		}

		elseif ($this->authType == 'CRAM-MD5') {
			$data	= explode(' ', $this->response);
			$data	= base64_decode($data[1]);
			$key	= str_pad($this->pwd, 64, chr(0x00));
			$ipad	= str_repeat(chr(0x36), 64);
			$opad	= str_repeat(chr(0x5c), 64);

			$this->put(base64_encode($this->user . ' ' . md5(($key ^ $opad) . md5(($key ^ $ipad) . $data, TRUE))) . self::CRLF);
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
		return !empty($this->response) && preg_match('~^' . $code . '~', $this->response);
	}

	/**
	 * collect log entries
	 */
	private function log($str) {
		$this->log[] = $str;
	}
}

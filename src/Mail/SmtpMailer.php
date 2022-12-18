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

use vxPHP\Mail\Exception\SmtpMailerException;

/**
 * simple SMTP mailer
 *
 * @author Gregor Kofler
 * @version 0.5.2 2021-09-30
 *
 * validity of email addresses is not checked
 * only encoding where necessary is applied
 */
class SmtpMailer implements MailerInterface
{
	public const CRLF = "\r\n";
    public const RFC5322_ATOM_CHARS	= "!#$%&'*+-/=?^_`{|}~a-zA-Z0-9";

    public const RFC_SERVICE_READY = 220;
    public const RFC_SERVICE_CLOSING = 221;
    public const RFC_AUTH_SUCCESSFUL = 235;
    public const RFC_REQUEST_OK = 250;
    public const RFC_CONTINUE_REQUEST = 334;
    public const RFC_START_MAIL_INPUT = 354;

    public const DEFAULT_PORT = 25;

	/**
	 * when set to true TLS is enforced in case server reports support
	 * @var boolean
	 */
    public const DEFAULT_TLS = true;

	/**
	 * preferences for MIME encoding
	 * used with mb_internal_encoding()
	 * mb_encode_mimeheader()
	 */
	public const MIME_ENCODING_PREFERENCES = [
		'scheme' => 'Q',
		'input-charset' => 'UTF-8',
		'output-charset' => 'UTF-8',
		'line-break-chars' => self::CRLF
	];

    /**
     * supported auth methods
     */
    public const AUTH_METHODS = ['NONE', 'LOGIN', 'PLAIN', 'CRAM-MD5'];


    /**
	 * host address of SMTP server
	 * @var string
	 */
	private	string $host;
	
	/**
	 * port of SMTP server
	 * @var integer
	 */
	private	int $port;

	/**
	 * username for authentication
	 * @var string
	 */
	private	string $user = '';

	/**
	 * password for authentication
	 * @var string
	 */
	private	string $pwd = '';

	/**
	 * auth method used for SMTP authentication
	 * @var string|null
     */
	private	string $authMethod = 'NONE';

	/**
	 * encryption method used for connection
	 * @var string|null
     */
	private ?string $smtpEncryption = null;

	/**
	 * supported encryption methods
	 * @var array
	 */
	private	array $smtpEncryptions = ['SSL', 'TLS'];

    /**
     * OAuth token
     * @var string
     */
    protected string $oAuthToken = '';

	/**
	 * extensions reported by EHLO/HELO
	 * 
	 * @var array
	 */
	private array $extensions = [];

	/**
	 * connection socket
	 * @var resource
	 */
	private	$socket;

	/**
	 * email address of sender
	 * @var string
	 */
	private	string $from = '';

	/**
	 * extracted display name, when from address is of form "display_name" <email_from>
	 * @var string
	 */
	private	string $fromDisplayName = '';

	/**
	 * holds receivers with email addresses
	 * @var array
	 */
	private	array $to = [];

	/**
	 * header rows
	 * @var array
	 */
	private	array $headers = [];

	/**
	 * the mail message
	 * @var string
	 */
	private	string $message = '';

	/**
	 * server response
	 * @var string
	 */
	private	string $response = '';

	/**
	 * server communication log
	 * @var array
	 */
	private	array $log = [];

    /**
     * constructor
     * configures only basic server settings
     * and default mime encoding
     *
     * @param string $host
     * @param int $port
     * @param string|null $smtpEncryption
     * @throws SmtpMailerException
     */
	public function __construct(string $host, int $port = self::DEFAULT_PORT, string $smtpEncryption = null)
    {
		if($smtpEncryption) {
			if(!in_array(strtoupper($smtpEncryption), $this->smtpEncryptions, true)) {
				throw new SmtpMailerException(sprintf("Invalid encryption type '%s'.", $smtpEncryption), SmtpMailerException::INVALID_ENCRYPTION_TYPE);
			}

			$this->smtpEncryption = strtolower($smtpEncryption);
		}

		$this->port = $port;
		$this->host	= $host;

		mb_internal_encoding(self::MIME_ENCODING_PREFERENCES['output-charset']);
	}

	/**
	 * establishes connection with SMTP server
	 * 
	 * @param integer $timeout
	 * 
	 * @throws SmtpMailerException
	 */
	public function connect(int $timeout = 10): void
    {
		if($this->smtpEncryption === 'ssl') {
			$protocol = $this->smtpEncryption . '://';
		}
		else {
			$protocol = '';
		}

		if($this->smtpEncryption === 'tls' && !function_exists('stream_socket_enable_crypto')) {
			throw new SmtpMailerException('TLS encryption not possible: stream_socket_enable_crypto() not found.', SmtpMailerException::TLS_FAILED);
		}

		$this->socket = @fsockopen($protocol . $this->host, $this->port, $errno, $errstr, $timeout);

		if (!$this->socket || !$this->check(self::RFC_SERVICE_READY)) {
			throw new SmtpMailerException(sprintf('Connection failed. %d: %s.', $errno, $errstr), SmtpMailerException::CONNECTION_FAILED);
		}

		stream_set_timeout($this->socket, $timeout, 0);
	}

	/**
	 * close connection
	 */
	public function close(): void
    {
		if($this->socket) {
			fclose($this->socket);
		}
	}

	/**
	 * set authentication data
	 * 
	 * @param string $user
	 * @param string $pwd
	 * @param string $authMethod
	 * 
	 * @throws SmtpMailerException
	 */
	public function setCredentials(string $user, string $pwd, string $authMethod = 'LOGIN'): void
    {
		$this->user = $user;
		$this->pwd = $pwd;

		if(!in_array(strtoupper($authMethod), self::AUTH_METHODS, true)) {
			throw new SmtpMailerException(sprintf("Invalid authentication type '%s'.", $authMethod), SmtpMailerException::INVALID_AUTH_TYPE);
		}

		$this->authMethod = strtoupper($authMethod);
	}

    /**
     * set oAuth token
     *
     * @param string $token
     */
	public function setOAuthToken (string $token): void
    {
        $this->oAuthToken = $token;
    }

	/**
	 * sets addressor
	 *
	 * @param string $from
	 */
	public function setFrom(string $from): void
    {
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
			$this->from = mb_encode_mimeheader($this->from, mb_internal_encoding(), self::MIME_ENCODING_PREFERENCES['scheme']);
		}
	}
	
	/**
	 * sets receiver
	 *
	 * @param mixed $to
	 */
	public function setTo($to): void
    {
		if(!is_array($to)) {
			$to = (array) $to;
		}

		$this->to = [];
		
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

			if(!in_array($email, $this->to, true)) {
				$this->to[] = $email;
			}
		}
	}

	/**
	 * set additional headers with associative array
	 * 
	 * @param array $headers
	 */
	public function setHeaders(array $headers): void
    {
		$this->headers = $headers;
	}

	/**
	 * set mail body
	 * 
	 * @param string $message
	 */
	public function setMessage(string $message): void
    {
		$this->message = $message;
	}
	
	/**
	 * sends ehlo, authentication, from, to headers, message and quit
	 */
	public function send(): void
    {
		// EHLO/HELO

		$ehlo = true;

		try {
			$this->sendEhlo();
		}

		catch(SmtpMailerException $e) {
			if($e->getCode() !== SmtpMailerException::EHLO_FAILED) {
				throw $e;
			}

			$ehlo = false;
			$this->sendHelo();
		}

		// TLS was configured or autostart TLS when STARTTLS is supported by server and no explicit encryption was set

		if(
		    $this->smtpEncryption === 'tls' ||
            (
                self::DEFAULT_TLS &&
                !$this->smtpEncryption &&
                isset($this->extensions['STARTTLS'])
            )
        ) {
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

        if($this->oAuthToken) {
            $this->authOAuthBearer();
        }
        else if($this->authMethod !== 'NONE') {
            $this->auth();
        }

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
	private function buildHeaderRows(): array
    {
		$rows = [];
		
		foreach(array_keys($this->headers) as $k) {
		
			switch(strtolower($k)) {
		
				// remove an optional bcc header
		
				case 'bcc':
					break;
		
				case 'subject':
				    if (preg_match('/^[\x20-\x7F]+$/', $this->headers[$k]) === 1) {
				        $rows[] = sprintf('%s: %s', $k, $this->headers[$k]);
                    }
                    else {
                        $rows[] = iconv_mime_encode($k, $this->headers[$k], self::MIME_ENCODING_PREFERENCES);
                    }
					break;
		
				case 'cc':
				case 'to':
					$addresses = preg_split('/\s*,\s*/', $this->headers[$k]);
					
					$encodedAddresses = [];

					foreach($addresses as $address) {

						$address = trim($address);
					
						// extract emails
					
						if(preg_match('~^(.*?)\s*<(.*?)>$~', $address, $matches)) {
							
							// encode display name
							
							$email = trim($matches[2]);
							$displayName = mb_encode_mimeheader(trim($matches[1]), mb_internal_encoding(), self::MIME_ENCODING_PREFERENCES['scheme']);
						}
						else {
							$email = $address;
							$displayName = null;
						}

						// encode email, when non-atom chars + "@" + "." are found in email

						if(!preg_match('/^[' . preg_quote(self::RFC5322_ATOM_CHARS, '/') . '@.]+$/', $email)) {
							$email = mb_encode_mimeheader($email, mb_internal_encoding(), self::MIME_ENCODING_PREFERENCES['scheme']);
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
						$displayName	= mb_encode_mimeheader(trim($matches[1]), mb_internal_encoding(), self::MIME_ENCODING_PREFERENCES['scheme']);
					}
					else {
						$email			= trim($this->headers[$k]);
						$displayName	= null;
					}

					// encode email, when non-atom chars + "@" + "." are found in email
					
					if(!preg_match('/^[' . preg_quote(self::RFC5322_ATOM_CHARS, '/') . '@.]+$/', $email)) {
						$email = mb_encode_mimeheader($email, mb_internal_encoding(), self::MIME_ENCODING_PREFERENCES['scheme']);
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
							mb_encode_mimeheader($this->fromDisplayName, mb_internal_encoding(), self::MIME_ENCODING_PREFERENCES['scheme']),
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
	private function sendEhlo(): void
    {
		$this->put("EHLO " . $this->host . self::CRLF);

		if (!$this->check(self::RFC_REQUEST_OK)) {
			throw new SmtpMailerException('Failed to send EHLO.', SmtpMailerException::EHLO_FAILED);
		}

		$this->parseExtensions($this->response);
	}

	/**
	 * sends helo
	 * 
	 * @throws SmtpMailerException
	 */
	private function sendHelo(): void
    {
		$this->put("HELO " . $this->host . self::CRLF);

		if (!$this->check(self::RFC_REQUEST_OK)) {
			throw new SmtpMailerException('Failed to send HELO.', SmtpMailerException::HELO_FAILED);
		}

		$this->parseExtensions($this->response);
	}

	
	/**
	 * parse HELO/EHLO response
	 * 
	 * @param string $response
	 */
	private function parseExtensions(string $response): void
    {
		$this->extensions = [];

		$rows = preg_split('/\r\n?/', trim($response));

		// skip first line
		
		array_shift($rows);
		
		foreach($rows as $row) {
			$data = trim(substr($row, 4));
		
			if(!$data) {
				continue;
			}
				
			$fields = preg_split('/[ =]/', $data);
			$name = array_shift($fields);
				
			switch($name) {
				case 'SIZE':
					$fields = $fields ? $fields[0] : 0;
					break;
				case 'AUTH':
					break;
				default:
					$fields = true;
			}
		
			$this->extensions[$name] = $fields;
		}
	}
	
	/**
	 * initiate TLS (encrypted) session
	 * 
	 * @throws SmtpMailerException
	 */
	private function startTLS(): void
    {
		$this->put('STARTTLS' . self::CRLF);
		
		if (!$this->check(self::RFC_SERVICE_READY)) {
			throw new SmtpMailerException('Failed to establish TLS.', SmtpMailerException::TLS_FAILED);
		}
		
		if(
			!stream_socket_enable_crypto(
				$this->socket,
				true,
				STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
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
	private function auth(): void
    {
		$this->put("AUTH " . $this->authMethod . self::CRLF);
		$this->getResponse();

		if( $this->response[0] !== '3') {
			throw new SmtpMailerException('Failed to send AUTH.', SmtpMailerException::AUTH_SEND_FAILED);
		}
	
		if ($this->authMethod === 'LOGIN') {
			$this->put(base64_encode($this->user) . self::CRLF);

			if(!$this->check(self::RFC_CONTINUE_REQUEST)) {
				throw new SmtpMailerException('Failed to send username.', SmtpMailerException::USERNAME_SEND_FAILED);
			}
	
			$this->put(base64_encode($this->pwd) . self::CRLF);
		}
		elseif ($this->authMethod === 'PLAIN') {
			$this->put(base64_encode($this->user . chr(0) . $this->user . chr(0) . $this->pwd) . self::CRLF);
		}

		elseif ($this->authMethod === 'CRAM-MD5') {
			$data = explode(' ', $this->response);
			$data = base64_decode($data[1]);
			$key = str_pad($this->pwd, 64, chr(0x00));
			$ipad = str_repeat(chr(0x36), 64);
			$opad = str_repeat(chr(0x5c), 64);

			$this->put(base64_encode($this->user . ' ' . md5(($key ^ $opad) . md5(($key ^ $ipad) . $data, true))) . self::CRLF);
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
	public function getLog(): array
    {
		return $this->log;
	}

	/**
	 * send command
	 *
	 * @param string $cmd
	 */
	private function put(string $cmd): void
    {
		fwrite($this->socket, $cmd);
		$this->log($cmd);
	}

	/**
	 * retrieve response of server
	 */
	private function getResponse(): void
    {
		$message = '';
		$continue = true;
		
		do {
			$tmp = fgets($this->socket, 1024);
			if($tmp === false) {
				$continue = false;
			}
			else {
				$message .= $tmp;
				//if(preg_match('~^[0-9]{3}~', $message)) {
				if(preg_match('~^(\d{3})(-(.*[\r\n]{1,2})+\\1)? [^\r\n]+[\r\n]{1,2}$~', $message)) {
					$continue = false;
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
	private function check(string $code): bool
    {
		$this->getResponse();
		return !empty($this->response) && preg_match('~^' . $code . '~', $this->response);
	}

    /**
     * collect log entries
     * @param string $message
     */
	private function log(string $message): void
    {
		$this->log[] = $message;
	}

    private function authOAuthBearer(): void
    {
        $chr1 = chr(1);
        $authStr = sprintf("n,a=%s,%shost=%s%sport=%s%sauth=Bearer %s%s%s",
            $this->from,
            $chr1,
            $this->host,
            $chr1,
            $this->port,
            $chr1,
            $this->oAuthToken,
            $chr1,
            $chr1
        );
        $authStr = base64_encode($authStr);
        $this->put('AUTH OAUTHBEARER ' . $authStr . self::CRLF);

        if(!$this->check(self::RFC_AUTH_SUCCESSFUL)) {
            throw new SmtpMailerException('Authentication failed.', SmtpMailerException::AUTH_FAILED);
        }
    }

    private function authXOAuth2(): void
    {
        $chr1 = chr(1);
        $authStr = sprintf("user=%s%sauth=Bearer %s%s%s",
            $this->from,
            $chr1,
            $this->oAuthToken,
            $chr1,
            $chr1
        );
        $authStr = base64_encode($authStr);
        $this->put('AUTH XOAUTH2 ' . $authStr . self::CRLF);

        if(!$this->check(self::RFC_AUTH_SUCCESSFUL)) {
            throw new SmtpMailerException('Authentication failed.', SmtpMailerException::AUTH_FAILED);
        }
    }
}

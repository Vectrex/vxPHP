<?php

namespace vxPHP\Mail;

use vxPHP\Mail\MailerInterface;
use vxPHP\Mail\Exception\MailerException;
use vxPHP\Application\Application;

/**
 * simple wrapper class for sending emails via mail()
 * or SmtpMailer
 *
 * @version 0.3.2 2015-02-23
 */

class Email {
	const CRLF = "\r\n";

	private	$mailer,
			$sender,
			$subject,
			$bcc,
			$cc,
			$receiver,
			$mailText,
			$sig,
			$htmlMail,
			$boundary,
			$headers = array(),
			$attachments = array();

	private static $debug = FALSE;


	/**
	 * mail constructor
	 * all parameters are optional
	 * 
	 * @param string $receiver
	 * @param string $subject
	 * @param string $mailText
	 * @param string $sender
	 * @param array $cc
	 * @param array $bcc
	 * @param string $sig
	 * @param string $htmlMail
	 */
	public function __construct($receiver = NULL, $subject = '(no subject)', $mailText = '', $sender = NULL, array $cc = array(), array $bcc = array(), $sig = '', $htmlMail = FALSE) {

		$this->receiver	= $receiver;
		$this->subject	= $subject;
		$this->mailText	= $mailText;
		$this->sender	= !empty($sender) ? $sender : (defined('DEFAULT_MAIL_SENDER') ? DEFAULT_MAIL_SENDER : 'mail@net.invalid');
		$this->cc		= $cc;
		$this->bcc		= $bcc;
		$this->sig		= $sig;
		$this->htmlMail	= $htmlMail;

		$this->encoding	= defined('DEFAULT_ENCODING') ? strtoupper(DEFAULT_ENCODING) : 'UTF-8';

	}

	/**
	 * set debug flag
	 * when true, emails will be dumped as HTML text boxes
	 * 
	 * @param boolean $state
	 */
	public static function setDebug($state) {

		self::$debug = (boolean) $state;

	}

	/**
	 * set receiving email address
	 * 
	 * @param string $receiver
	 * @return \vxPHP\Mail\Email
	 */
	public function setReceiver($receiver) {

		$this->receiver = $receiver;
		return $this;

	}

	/**
	 * set sending email address
	 * 
	 * @param string $sender
	 * @return \vxPHP\Mail\Email
	 */
	public function setSender($sender) {

		$this->sender = $sender;
		return $this;

	}

	/**
	 * set mail text
	 * 
	 * @param string $text
	 * @return \vxPHP\Mail\Email
	 */
	public function setMailText($text) {

		$this->mailText = $text;
		return $this;

	}

	/**
	 * set signature for mail
	 * will be appended to mail text with correct delimiter
	 * 
	 * @param string $signature
	 * @return \vxPHP\Mail\Email
	 */
	public function setSig($signature) {

		$this->sig = $signature;
		return $this;

	}

	/**
	 * set subject
	 * 
	 * @param string $subject
	 * @return \vxPHP\Mail\Email
	 */
	public function setSubject($subject) {

		$this->subject = $subject;
		return $this;

	}

	/**
	 * set BCC receivers
	 * 
	 * @param array $bcc
	 * @return \vxPHP\Mail\Email
	 */
	public function setBcc(array $bcc) {

		$this->bcc = $bcc;
		return $this;

	}

	/**
	 * set CC receivers
	 * 
	 * @param array $cc
	 * @return \vxPHP\Mail\Email
	 */
	public function setCc(array $cc) {

		$this->cc = $cc;
		return $this;

	}

	/**
	 * set flag to indicate a HTML mail
	 * will be observed upon mail body generation
	 * 
	 * @param boolean $flag
	 * @return \vxPHP\Mail\Email
	 */
	public function setHtmlMail($flag) {

		$this->htmlMail = $flag;
		return $this;

	}

	/**
	 * attach file $filePath; use name $filename in mail 
	 * 
	 * @param string $filePath
	 * @param string $filename
	 * 
	 * @return \vxPHP\Mail\Email
	 */
	public function addAttachment($filePath, $filename = NULL) {

		if(file_exists($filePath)) {
			$this->attachments[] = array('path' => $filePath, 'filename' => $filename);
		}
		
		return $this;

	}

	/**
	 * send mail
	 * with Email::$debug set to TRUE,
	 * a textbox with the mail contents is dumped
	 * 
	 * @return boolean
	 */
	public function send()	{

		$this->buildHeaders();
		$this->buildMsg();

		if (!self::$debug) {
			return $this->sendMail();
		}

		$headers = array();
		foreach($this->headers as $k => $v) {
			$headers[] = "$k: $v";
		}
		echo '<div style="border: solid 2px #888; background:#efe; font-family: monospace; font-size: 1em; padding: 1em; margin: 1em;">';
		echo $this->receiver, '<hr>';
		echo implode('<br>', $headers), '<hr>', nl2br($this->msg);
		echo '</div>';

		return TRUE;

	}

	/**
	 * evaluate mailer class and send mail
	 * 
	 * @return boolean
	 */
	private function sendMail() {

		// check for configured mailer

		if(is_null($this->mailer) && !is_null(Application::getInstance()->getConfig()->mail->mailer)) {

			$mailer			= Application::getInstance()->getConfig()->mail->mailer;
			$reflection		= new \ReflectionClass(str_replace('/', '\\', $mailer->class));
			$this->mailer	= $reflection->newInstanceArgs(array($mailer->host, $mailer->port));

			if(isset($mailer->auth_type)) {
				$this->mailer->setCredentials($mailer->user, $mailer->pass, $mailer->auth_type);
			}
			else {
				$this->mailer->setCredentials($mailer->user, $mailer->pass);
			}
		}

		if(is_null($this->mailer)) {

			// plain mail() function

			$headers = array();

			foreach($this->headers as $k => $v) {
				$headers[] = "$k: $v";
			}

			return mail($this->receiver, $this->subject, $this->msg, implode(self::CRLF, $headers));
		}

		else {

			// send mail with configured mailer

			try {
				$this->mailer->connect();

				$this->mailer->setFrom($this->sender);
				$this->mailer->setTo(array_merge((array) $this->receiver, $this->cc, $this->bcc));
				$this->mailer->setHeaders(array_merge(
					array(
						'To'		=> $this->receiver,
						'Subject'	=> $this->subject
					),
					$this->headers
				));
				$this->mailer->setMessage($this->msg);
				$this->mailer->send();

				$this->mailer->close();
				return TRUE;
			}

			catch(MailerException $e) {
				$this->mailer->close();
				return $e->getMessage();
			}
		}
	}

	/**
	 * explicitly set mailer
	 *
	 * @param Mailer $mailer
	 * @return \vxPHP\Mail\Email
	 */
	public function setMailer(MailerInterface $mailer) {

		$this->mailer = $mailer;
		return $this;

	}
	
	/**
	 * get previously set mailer
	 * 
	 * @return MailerInterface
	 */
	public function getMailer() {

		return $this->mailer;

	}

	/**
	 * fill headers array
	 */
	private function buildHeaders() {

		$this->headers = array(
			'From'			=> $this->sender,
			'Return-Path'	=> $this->sender,
			'Reply-To'		=> $this->sender,
			'Date'			=> date('r'),
			'Message-ID'	=> '<'.sha1(microtime()).'@'.substr($this->sender, strpos($this->sender, '@') + 1).'>',
			'User-Agent'	=> 'vxPHP SmtpMailer',
			'X-Mailer'		=> 'PHP'.phpversion(),
			'MIME-Version'	=> '1.0'
		);

		if(!empty($this->cc)) {
			$this->headers['CC'] = implode(',', $this->cc);
		}

		if(!empty($this->bcc)) {
			$this->headers['BCC'] = implode(',', $this->bcc);
		}

		if(count($this->attachments) > 0) {
			$this->boundary = '!!!@snip@here@!!!';
			$this->headers['Content-type'] = 'multipart/mixed; boundary="'.$this->boundary.'"';
		}
		else {
			$this->headers['Content-type'] = 'text/'.($this->htmlMail ? 'html' : 'plain')."; charset={$this->encoding}";
		}

	}
	
	/**
	 * build message body
	 */
	private function buildMsg() {

		if(isset($this->boundary)) {
			$this->msg = '--'.$this->boundary.self::CRLF;
			$this->msg .= 'Content-type: text/'.($this->htmlMail ? 'html' : 'plain')."; charset={$this->encoding}".self::CRLF;
			$this->msg .= 'Content-Transfer-Encoding: 8bit'.self::CRLF.self::CRLF;
		}
		else {
			$this->msg = '';
		}

		$this->msg .=	$this->mailText.self::CRLF;
		$this->msg .=	empty($this->sig) || $this->htmlMail ? self::CRLF : self::CRLF.self::CRLF.'-- '.self::CRLF.$this->sig.self::CRLF;

		foreach($this->attachments as $f) {
			$filename = empty($f['filename']) ? basename($f['path']) : $f['filename'];

			$this->msg .= '--'.$this->boundary.self::CRLF;
			$this->msg .= 'Content-Type: application/octet-stream; name="'.$filename.'"'.self::CRLF;
			$this->msg .= 'Content-Disposition: attachment; filename="'.$filename.'"'.self::CRLF;
			$this->msg .= 'Content-Transfer-Encoding: base64'.self::CRLF.self::CRLF;
			$this->msg .= rtrim(chunk_split(base64_encode(file_get_contents($f['path'])),72,self::CRLF));
		}

	}
}

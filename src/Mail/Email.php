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

use vxPHP\Application\Exception\ApplicationException;
use vxPHP\Application\Application;

/**
 * simple wrapper class for sending emails via mail()
 * or SmtpMailer
 * 
 * no validation of email addresses is performed
 *
 * @version 0.6.0 2020-10-11
 */

class Email {
	public const CRLF = "\r\n";

    /**
     * @var \vxPHP\Mail\MailerInterface
     */
	private $mailer;

    /**
     * @var string
     */
	private $sender;

    /**
     * @var string
     */
	private	$subject;

    /**
     * @var array
     */
    private	$bcc;

    /**
     * @var array
     */
    private	$cc;

    /**
     * @var array
     */
    private $receiver;

    /**
     * @var string
     */
    private	$mailText;

    /**
     * @var string
     */
    private	$sig;

    /**
     * @var bool
     */
    private	$htmlMail;

    /**
     * @var string
     */
    private	$boundary;

    /**
     * @var array
     */
    private	$headers = [];

    /**
     * @var array
     */
    private	$attachments = [];

    /**
     * @var string
     */
    private	$encoding;

    /**
     * the message body
     *
     * @var string
     */
	private $msg;

    /**
     * @var bool
     */
	private static $debug = false;


    /**
     * mail constructor
     * all parameters are optional
     *
     * @param mixed $receiver
     * @param string $subject
     * @param string $mailText
     * @param string $sender
     * @param array $cc
     * @param array $bcc
     * @param string $sig
     * @param boolean $htmlMail
     */
	public function __construct($receiver = null, string $subject = '(no subject)', string $mailText = '', string $sender = '', array $cc = [], array $bcc = [], $sig = '', $htmlMail = false)
    {
		$this->receiver	= (array) $receiver;
		$this->subject = $subject;
		$this->mailText = $mailText;
		$this->sender = $sender ?: (defined('DEFAULT_MAIL_SENDER') ? DEFAULT_MAIL_SENDER : 'mail@net.invalid');
		$this->cc = $cc;
		$this->bcc = $bcc;
		$this->sig = $sig;
		$this->htmlMail = $htmlMail;

		$this->encoding = defined('DEFAULT_ENCODING') ? strtoupper(DEFAULT_ENCODING) : 'UTF-8';
	}

	/**
	 * set debug flag
	 * when true, emails will be dumped as HTML text boxes
	 * 
	 * @param boolean $state
	 */
	public static function setDebug(bool $state): void
    {
		self::$debug = $state;
	}

	/**
	 * set receiving email address (string) or addresses (array)
	 * 
	 * @param mixed $receiver
	 * @return \vxPHP\Mail\Email
	 */
	public function setReceiver($receiver): Email
    {
		$this->receiver = (array) $receiver;
		return $this;
	}

	/**
	 * set sending email address
	 * 
	 * @param string $sender
	 * @return \vxPHP\Mail\Email
	 */
	public function setSender(string $sender): Email
    {
		$this->sender = $sender;
		return $this;
	}

	/**
	 * set mail text
	 * 
	 * @param string $text
	 * @return \vxPHP\Mail\Email
	 */
	public function setMailText(string $text): Email
    {
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
	public function setSig(string $signature): Email
    {
		$this->sig = $signature;
		return $this;
	}

	/**
	 * set subject
	 * 
	 * @param string $subject
	 * @return \vxPHP\Mail\Email
	 */
	public function setSubject(string $subject): Email
    {
		$this->subject = $subject;
		return $this;
	}

	/**
	 * set BCC receivers
	 * 
	 * @param array $bcc
	 * @return \vxPHP\Mail\Email
	 */
	public function setBcc(array $bcc): Email
    {
		$this->bcc = $bcc;
		return $this;
	}

	/**
	 * set CC receivers
	 * 
	 * @param array $cc
	 * @return \vxPHP\Mail\Email
	 */
	public function setCc(array $cc): Email
    {
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
	public function setHtmlMail(bool $flag): Email
    {
		$this->htmlMail = $flag;
		return $this;
	}

	/**
	 * attach file $filePath; use $filename as name in mail
	 * 
	 * @param string $filePath
	 * @param string $filename
	 * 
	 * @return \vxPHP\Mail\Email
	 */
	public function addAttachment(string $filePath, string $filename = ''): Email
    {
		if(file_exists($filePath)) {
			$this->attachments[] = ['path' => $filePath, 'filename' => $filename ?: basename($filePath)];
		}
		
		return $this;
	}

    /**
     * add (binary) data as attachment; use $filename as name in email
     *
     * @param string $data
     * @param string $filename
     * @return \vxPHP\Mail\Email
     */
	public function addAttachmentData(string $data, string $filename): Email
    {
	    $this->attachments[] = ['data' => $data, 'filename' => $filename];
	    return $this;
    }

    /**
     * send mail
     * with Email::$debug set to TRUE,
     * a textbox with the mail contents is dumped
     *
     * @return boolean
     * @throws \ReflectionException
     * @throws ApplicationException
     */
	public function send(): bool
    {
		$this->buildHeaders();
		$this->buildMsg();

		if (!self::$debug) {
			return $this->sendMail();
		}

		$headers = [];
		foreach($this->headers as $k => $v) {
			$headers[] = "$k: $v";
		}
		echo '<div style="border: solid 2px #888; background:#efe; font-family: monospace; font-size: 1em; padding: 1em; margin: 1em;">';
		echo is_array($this->receiver) ? implode(', ', $this->receiver) : $this->receiver, '<hr>';
		echo implode('<br>', $headers), '<hr>', $this->subject, '<hr>', nl2br($this->msg);
		echo '</div>';

		return true;
	}

    /**
     * evaluate mailer class and send mail
     *
     * @return boolean
     * @throws \ReflectionException
     * @throws ApplicationException
     */
	private function sendMail(): bool
    {
		// check for configured mailer

		if(is_null($this->mailer) && !is_null(Application::getInstance()->getConfig()->mail->mailer)) {

			$mailer = Application::getInstance()->getConfig()->mail->mailer;
			$reflection = new \ReflectionClass(str_replace('/', '\\', $mailer->class));

			$port = $mailer->port ?? null;
			$encryption = $mailer->encryption ?? null;

			$this->mailer = $reflection->newInstanceArgs([$mailer->host, $port, $encryption]);

			if(isset($mailer->oauth_token)) {
			    $this->mailer->setOAuthToken($mailer->oauth_token);
            }
			else if(isset($mailer->auth_type, $mailer->user, $mailer->pass)) {
				$this->mailer->setCredentials($mailer->user, $mailer->pass, $mailer->auth_type);
			}
			else {
				$this->mailer->setCredentials('', '', 'NONE');
			}
		}

		if(is_null($this->mailer)) {

			// use PHP's own mail() function

			$headers = [];

			foreach($this->headers as $k => $v) {
				$headers[] = iconv_mime_encode($k, $v);
			}

			mb_internal_encoding($this->encoding);

			// @todo ensure receiver to be RFC conforming

			return mail(
				implode(',', $this->receiver),
				mb_encode_mimeheader($this->subject, mb_internal_encoding(), 'Q'),
				$this->msg,
				implode(self::CRLF, $headers)
			);
		}

        // send mail with configured mailer

        try {
            $this->mailer->connect();

            $this->mailer->setFrom($this->sender);
            $this->mailer->setTo(array_merge($this->receiver, $this->cc, $this->bcc));
            $this->mailer->setHeaders(array_merge(
                [
                    'To' => implode(',', $this->receiver),
                    'Subject' => $this->subject
                ],
                $this->headers
            ));
            $this->mailer->setMessage($this->msg);
            $this->mailer->send();

            $this->mailer->close();
            return true;
        }

        catch(\Exception $e) {
            $this->mailer->close();
            throw $e;
        }
    }

	/**
	 * explicitly set mailer
	 *
	 * @param MailerInterface $mailer
	 * @return \vxPHP\Mail\Email
	 */
	public function setMailer(MailerInterface $mailer): Email
    {
		$this->mailer = $mailer;
		return $this;
	}
	
	/**
	 * get previously set mailer
	 * 
	 * @return MailerInterface
	 */
	public function getMailer(): MailerInterface
    {
		return $this->mailer;
	}

	/**
	 * fill headers array
	 */
	private function buildHeaders(): void
    {
		$this->headers = [
			'From' => $this->sender,
			'Return-Path' => $this->sender,
			'Reply-To' => $this->sender,
			'Date' => (new \DateTime())->format('r'),
			'Message-ID' => '<'.sha1(microtime()) . '@' . substr($this->sender, strpos($this->sender, '@') + 1) . '>',
			'User-Agent' => 'vxPHP SmtpMailer',
			'X-Mailer' => 'PHP' . PHP_VERSION,
			'MIME-Version' => '1.0'
		];

		if(!empty($this->cc)) {
			$this->headers['CC'] = implode(',', $this->cc);
		}

		if(!empty($this->bcc)) {
			$this->headers['BCC'] = implode(',', $this->bcc);
		}

		if(count($this->attachments) > 0) {
			$this->boundary = '!!!@snip@here@!!!';
			$this->headers['Content-type'] = sprintf('multipart/mixed; boundary="%s"', $this->boundary);
		}
		else {
			$this->headers['Content-type'] = sprintf('text/%s; charset=%s', $this->htmlMail ? 'html' : 'plain', $this->encoding);
		}
	}
	
	/**
	 * build message body
	 */
	private function buildMsg(): void
    {
		if(isset($this->boundary)) {
			$this->msg = '--' . $this->boundary . self::CRLF;
			$this->msg .= 'Content-type: text/' . ($this->htmlMail ? 'html' : 'plain') . '; charset=' .$this->encoding . self::CRLF;
			$this->msg .= 'Content-Transfer-Encoding: 8bit' . self::CRLF . self::CRLF;
		}
		else {
			$this->msg = '';
		}

		$this->msg .= $this->mailText . self::CRLF;
		
		// add signature

		if(!empty($this->sig) && !$this->htmlMail) {
			$this->msg .= self::CRLF . self::CRLF . '-- ' . self::CRLF . $this->sig;
		}
			
		if(!count($this->attachments)) {
			$this->msg .= self::CRLF;
		}
		else {

			foreach($this->attachments as $f) {

			    // attached data always has a filename set

				$filename = empty($f['filename']) ? basename($f['path']) : $f['filename'];
	
				$this->msg .= self::CRLF . '--' . $this->boundary . self::CRLF;
				$this->msg .= sprintf('Content-Type: application/octet-stream; name="%s"', $filename) . self::CRLF;
				$this->msg .= sprintf('Content-Disposition: attachment; filename="%s"', $filename) . self::CRLF;
				$this->msg .= 'Content-Transfer-Encoding: base64' . self::CRLF . self::CRLF;

				if(isset($f['data'])) {
                    $this->msg .= rtrim(chunk_split(base64_encode($f['data']), 72, self::CRLF));
                }
				else {
                    $this->msg .= rtrim(chunk_split(base64_encode(file_get_contents($f['path'])), 72, self::CRLF));
                }
	
			}
			
			$this->msg .= self::CRLF . '--'. $this->boundary . '--' . self::CRLF;
		}
	}
}

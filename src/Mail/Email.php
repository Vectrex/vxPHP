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

    use vxPHP\Application\Application;
    use vxPHP\Application\Exception\ApplicationException;

    /**
     * simple wrapper class for sending emails via mail()
     * or SmtpMailer
     *
     * no validation of email addresses is performed
     *
     * @version 0.6.4 2026-06-11
     */
    class Email
    {
        public const string CRLF = "\r\n";

        /**
         * @var MailerInterface|null
         */
        private ?MailerInterface $mailer = null;

        /**
         * @var string|null
         */
        private ?string $boundary = null;

        /**
         * @var array
         */
        private array $headers = [];

        /**
         * @var array
         */
        private array $attachments = [];

        /**
         * @var string
         */
        private string $encoding;

        /**
         * the message body
         *
         * @var string
         */
        private string $msg;

        /**
         * @var bool
         */
        private static bool $debug = false;


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
        public function __construct(private string|array $receiver = [], private string $subject = '(no subject)', private string $mailText = '', private string $sender = '', private array $cc = [], private array $bcc = [], private string $sig = '', private bool $htmlMail = false)
        {
            $this->receiver = (array) $this->receiver;
            $this->sender = $this->sender ?: (defined('DEFAULT_MAIL_SENDER') ? DEFAULT_MAIL_SENDER : 'mail@net.invalid');
            $this->encoding = defined('DEFAULT_ENCODING') ? strtoupper(DEFAULT_ENCODING) : 'UTF-8';
        }

        /**
         * set a debug flag
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
         * @return Email
         */
        public function setReceiver(array|string $receiver): self
        {
            $this->receiver = (array)$receiver;
            return $this;
        }

        /**
         * set sending email address
         *
         * @param string $sender
         * @return Email
         */
        public function setSender(string $sender): self
        {
            $this->sender = $sender;
            return $this;
        }

        /**
         * set mail text
         *
         * @param string $text
         * @return Email
         */
        public function setMailText(string $text): self
        {
            $this->mailText = $text;
            return $this;
        }

        /**
         * set signature for mail
         * will be appended to mail text with the correct delimiter
         *
         * @param string $signature
         * @return Email
         */
        public function setSig(string $signature): self
        {
            $this->sig = $signature;
            return $this;
        }

        /**
         * set subject
         *
         * @param string $subject
         * @return Email
         */
        public function setSubject(string $subject): self
        {
            $this->subject = $subject;
            return $this;
        }

        /**
         * set BCC receivers
         *
         * @param array $bcc
         * @return Email
         */
        public function setBcc(array $bcc): self
        {
            $this->bcc = $bcc;
            return $this;
        }

        /**
         * set CC receivers
         *
         * @param array $cc
         * @return Email
         */
        public function setCc(array $cc): self
        {
            $this->cc = $cc;
            return $this;
        }

        /**
         * set a flag to indicate HTML mail
         * will be observed upon mail body generation
         *
         * @param boolean $flag
         * @return Email
         */
        public function setHtmlMail(bool $flag): self
        {
            $this->htmlMail = $flag;
            return $this;
        }

        /**
         * attach the file $filePath; use $filename as the name in mail
         *
         * @param string $filePath
         * @param string $filename
         *
         * @return Email
         */
        public function addAttachment(string $filePath, string $filename = ''): self
        {
            if (file_exists($filePath)) {
                $this->attachments[] = ['path' => $filePath, 'filename' => $filename ?: basename($filePath)];
            }

            return $this;
        }

        /**
         * add (binary) data as an attachment; use $filename as the name in email
         *
         * @param string $data
         * @param string $filename
         * @return Email
         */
        public function addAttachmentData(string $data, string $filename): self
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
            foreach ($this->headers as $k => $v) {
                $headers[] = "$k: $v";
            }
            echo '<div style="border: solid 2px #888; background:#efe; font-family: monospace; font-size: 1em; padding: 1em; margin: 1em;">';
            echo implode(', ', $this->receiver), '<hr>';
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

            if (is_null($this->mailer) && !is_null(Application::getInstance()->getConfig()->mail->mailer)) {

                $mailer = Application::getInstance()->getConfig()->mail->mailer;
                $reflection = new \ReflectionClass(str_replace('/', '\\', $mailer->class));

                $port = $mailer->port ?? null;
                $encryption = $mailer->encryption ?? null;

                $this->mailer = $reflection->newInstanceArgs([$mailer->host, $port, $encryption]);

                if (isset($mailer->oauth_token)) {
                    $this->mailer->setOAuthToken($mailer->oauth_token);
                } else if (isset($mailer->auth_type, $mailer->user, $mailer->pass)) {
                    $this->mailer->setCredentials($mailer->user, $mailer->pass, $mailer->auth_type);
                } else {
                    $this->mailer->setCredentials('', '', 'NONE');
                }
            }

            if (is_null($this->mailer)) {

                // use PHP's own mail() function

                $headers = [];

                foreach ($this->headers as $k => $v) {
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
            } catch (\Exception $e) {
                $this->mailer->close();
                throw $e;
            }
        }

        /**
         * explicitly set mailer
         *
         * @param MailerInterface $mailer
         * @return Email
         */
        public function setMailer(MailerInterface $mailer): self
        {
            $this->mailer = $mailer;
            return $this;
        }

        /**
         * get previously set mailer
         *
         * @return MailerInterface|null
         */
        public function getMailer(): ?MailerInterface
        {
            return $this->mailer;
        }

        /**
         * fill the header's array
         */
        private function buildHeaders(): void
        {
            $this->headers = [
                'From' => $this->sender,
                'Return-Path' => $this->sender,
                'Reply-To' => $this->sender,
                'Date' => (new \DateTime())->format('r'),
                'Message-ID' => '<' . sha1(microtime()) . '@' . substr($this->sender, strpos($this->sender, '@') + 1) . '>',
                'User-Agent' => 'vxPHP SmtpMailer',
                'X-Mailer' => 'PHP' . PHP_VERSION,
                'MIME-Version' => '1.0'
            ];

            if (!empty($this->cc)) {
                $this->headers['CC'] = implode(',', $this->cc);
            }

            if (!empty($this->bcc)) {
                $this->headers['BCC'] = implode(',', $this->bcc);
            }

            if (count($this->attachments) > 0) {
                $this->boundary = '!!!@snip@here@!!!';
                $this->headers['Content-type'] = sprintf('multipart/mixed; boundary="%s"', $this->boundary);
            } else {
                $this->headers['Content-type'] = sprintf('text/%s; charset=%s', $this->htmlMail ? 'html' : 'plain', $this->encoding);
            }
        }

        /**
         * build message body
         */
        private function buildMsg(): void
        {
            if (isset($this->boundary)) {
                $this->msg = '--' . $this->boundary . self::CRLF;
                $this->msg .= 'Content-type: text/' . ($this->htmlMail ? 'html' : 'plain') . '; charset=' . $this->encoding . self::CRLF;
                $this->msg .= 'Content-Transfer-Encoding: 8bit' . self::CRLF . self::CRLF;
            } else {
                $this->msg = '';
            }

            $this->msg .= $this->mailText . self::CRLF;

            // add signature

            if (!empty($this->sig) && !$this->htmlMail) {
                $this->msg .= self::CRLF . self::CRLF . '-- ' . self::CRLF . $this->sig;
            }

            if (!count($this->attachments)) {
                $this->msg .= self::CRLF;
            } else {

                foreach ($this->attachments as $f) {

                    // attached data always has a filename set

                    $filename = empty($f['filename']) ? basename($f['path']) : $f['filename'];

                    $this->msg .= self::CRLF . '--' . $this->boundary . self::CRLF;

                    $this->msg .= sprintf('Content-Type: application/octet-stream; name="%s"', $filename) . self::CRLF;
                    $this->msg .= sprintf('Content-Disposition: attachment; filename="%s"', $filename) . self::CRLF;
                    $this->msg .= 'Content-Transfer-Encoding: base64' . self::CRLF . self::CRLF;

                    if (isset($f['data'])) {

                        // check whether data is already a base64 encoded string

                        if (preg_match('/^data:.*?;base64,(.*)/', $f['data'], $matches)) {
                            $this->msg .= rtrim(chunk_split($matches[1], 72, self::CRLF));
                        } else {
                            $this->msg .= rtrim(chunk_split(base64_encode($f['data']), 72, self::CRLF));
                        }
                    } else {
                        $this->msg .= rtrim(chunk_split(base64_encode(file_get_contents($f['path'])), 72, self::CRLF));
                    }

                }

                $this->msg .= self::CRLF . '--' . $this->boundary . '--' . self::CRLF;
            }
        }
    }

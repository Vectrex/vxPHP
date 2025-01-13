<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Mail\Exception;

class SmtpMailerException extends MailerException
{
	public const int CONNECTION_FAILED = 1;
    public const int EHLO_FAILED = 2;
    public const int HELO_FAILED = 3;
    public const int TLS_FAILED = 4;
    public const int AUTH_SEND_FAILED = 5;
    public const int USERNAME_SEND_FAILED = 6;
    public const int AUTH_FAILED = 7;
    public const int ADDRESSOR_SEND_FAILED = 8;
    public const int RCPT_SEND_FAILED = 9;
    public const int DATA_TRANSFER_FAILED = 10;
    public const int INVALID_AUTH_TYPE = 11;
    public const int INVALID_ENCRYPTION_TYPE = 12;
}
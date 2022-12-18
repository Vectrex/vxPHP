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

use vxPHP\Mail\Exception\MailerException;

class SmtpMailerException extends MailerException
{
	public const CONNECTION_FAILED = 1;
    public const EHLO_FAILED = 2;
    public const HELO_FAILED = 3;
    public const TLS_FAILED = 4;
    public const AUTH_SEND_FAILED = 5;
    public const USERNAME_SEND_FAILED = 6;
    public const AUTH_FAILED = 7;
    public const ADDRESSOR_SEND_FAILED = 8;
    public const RCPT_SEND_FAILED = 9;
    public const DATA_TRANSFER_FAILED = 10;
    public const INVALID_AUTH_TYPE = 11;
    public const INVALID_ENCRYPTION_TYPE = 12;
}
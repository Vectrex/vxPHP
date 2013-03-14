<?php

namespace vxPHP\Mail\Exception;

use vxPHP\Mail\Exception\MailerException;

class SmtpMailerException extends MailerException {
	const CONNECTION_FAILED		= 1;
	const EHLO_FAILED			= 2;
	const HELO_FAILED			= 3;
	const AUTH_SEND_FAILED		= 4;
	const USERNAME_SEND_FAILED	= 5;
	const AUTH_FAILED			= 6;
	const ADDRESSOR_SEND_FAILED	= 7;
	const RCPT_SEND_FAILED		= 8;
	const DATA_TRANSFER_FAILED	= 9;
	const INVALID_AUTH_TYPE		= 10;
}

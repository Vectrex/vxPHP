<?php
namespace vxPHP\Http;

/**
 * class with constants and descriptions for http status
 *
 * @author Gregor Kofler
 *
 * @version 0.1.0 2013-05-10
 *
 */
class StatusCode {

	const	HTTP_CONTINUE						= 100;
	const	HTTP_SWITCHING_PROTOCOLS			= 101;
	const	HTTP_OK								= 200;
	const	HTTP_CREATED						= 201;
	const	HTTP_ACCEPTED						= 202;
	const	HTTP_NON_AUTHORITATIVE_INFORMATION	= 203;
	const	HTTP_NO_CONTENT						= 204;
	const	HTTP_RESET_CONTENT					= 205;
	const	HTTP_PARTIAL_CONTENT				= 206;
	const	HTTP_MULTIPLE_CHOICES				= 300;
	const	HTTP_MOVED_PERMANENTLY				= 301;
	const	HTTP_FOUND							= 302;
	const	HTTP_SEE_OTHER						= 303;
	const	HTTP_NOT_MODIFIED					= 304;
	const	HTTP_USE_PROXYT						= 305;
	const	HTTP_TEMPORARY_REDIRECT				= 307;
	const	HTTP_BAD_REQUEST					= 400;
	const	HTTP_UNAUTHORIZED					= 401;
	const	HTTP_PAYMENT_REQUIRED				= 402;
	const	HTTP_FORBIDDEN						= 403;
	const	HTTP_NOT_FOUND						= 404;
	const	HTTP_METHOD_NOT_ALLOWED				= 405;
	const	HTTP_NOT_ACCEPTABLE					= 406;
	const	HTTP_PROXY_AUTHENTICATION_REQUIRED	= 407;
	const	HTTP_REQUEST_TIMEOUT				= 408;
	const	HTTP_CONFLICT						= 409;
	const	HTTP_GONE							= 410;
	const	HTTP_LENGTH_REQUIRED				= 411;
	const	HTTP_PRECONDITION_FAILED			= 412;
	const	HTTP_REQUEST_ENTITY_TOO_LARGE		= 413;
	const	HTTP_REQUEST_URI_TOO_LARGE			= 414;
	const	HTTP_UNSUPPORTED_MEDIA_TYPE			= 415;
	const	HTTP_REQUESTED_RANGE_NOT_SATISFIABLE= 416;
	const	HTTP_EXPECTATION_FAILED				= 417;
	const	HTTP_INTERNAL_SERVER_ERROR			= 500;
	const	HTTP_NOT_IMPLEMENTED				= 501;
	const	HTTP_BAD_GATEWAY					= 502;
	const	HTTP_SERVICE_UNAVAILABLE			= 503;
	const	HTTP_GATEWAY_TIMEOUT				= 504;
	const	HTTP_HTTP_VERSION_NOT_SUPPORTED		= 505;

	public static $code = array(
		100 => 'Continue',
		101 => 'Switching Protocols',
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		307 => 'Temporary Redirect',
		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Large',
		415 => 'Unsupported Media Type',
		416 => 'Requested Range Not Satisfiable',
		417 => 'Expectation Failed',
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported'
	);
}

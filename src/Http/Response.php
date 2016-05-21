<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


/*
 * with minor adaptations lifted from Symfony's HttpFoundation classes
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 */

namespace vxPHP\Http;

/**
 * Response represents an HTTP response.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Response {

	const	HTTP_CONTINUE						= 100;
	const	HTTP_SWITCHING_PROTOCOLS			= 101;
	const	HTTP_PROCESSING						= 102;
	const	HTTP_OK								= 200;
	const	HTTP_CREATED						= 201;
	const	HTTP_ACCEPTED						= 202;
	const	HTTP_NON_AUTHORITATIVE_INFORMATION	= 203;
	const	HTTP_NO_CONTENT						= 204;
	const	HTTP_RESET_CONTENT					= 205;
	const	HTTP_PARTIAL_CONTENT				= 206;
	const	HTTP_MULTI_STATUS					= 207;
	const	HTTP_ALREADY_REPORTED				= 208;
	const	HTTP_IM_USED						= 226;
	const	HTTP_MULTIPLE_CHOICES				= 300;
	const	HTTP_MOVED_PERMANENTLY				= 301;
	const	HTTP_FOUND							= 302;
	const	HTTP_SEE_OTHER						= 303;
	const	HTTP_NOT_MODIFIED					= 304;
	const	HTTP_USE_PROXYT						= 305;
	const	HTTP_RESERVED						= 306;
	const	HTTP_TEMPORARY_REDIRECT				= 307;
	const	HTTP_PERMANENTLY_REDIRECT			= 308;
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
	const	HTTP_I_AM_A_TEAPOT					= 418;
	const	HTTP_UNPROCESSABLE_ENTITY			= 422;
	const	HTTP_LOCKED							= 423;
	const	HTTP_FAILED_DEPENDENCY				= 424;
	const	HTTP_RESERVED_FOR_WEBDAV_ADVANCED_COLLECTIONS_EXPIRED_PROPOSAL	= 425;
	const	HTTP_UPGRADE_REQUIRED				= 426;
	const	HTTP_PRECONDITION_REQUIRED			= 428;
	const	HTTP_TOO_MANY_REQUESTS				= 429;
	const	HTTP_REQUEST_HEADER_FIELDS_TOO_LARGE= 431;
	const	HTTP_UNAVAILABLE_FOR_LEGAL_REASONS	= 451;
	const	HTTP_INTERNAL_SERVER_ERROR			= 500;
	const	HTTP_NOT_IMPLEMENTED				= 501;
	const	HTTP_BAD_GATEWAY					= 502;
	const	HTTP_SERVICE_UNAVAILABLE			= 503;
	const	HTTP_GATEWAY_TIMEOUT				= 504;
	const	HTTP_HTTP_VERSION_NOT_SUPPORTED		= 505;
	const	HTTP_VARIANT_ALSO_NEGOTIATES_EXPERIMENTAL	= 501;
	const	HTTP_INSUFFICIENT_STORAGE			= 502;
	const	HTTP_LOOP_DETECTED					= 503;
	const	HTTP_NOT_EXTENDED					= 504;
	const	HTTP_NETWORK_AUTHENTICATION_REQUIRED= 505;

	/**
	 * @var ResponseHeaderBag
	 */
	public $headers;

	/**
	 * @var string
	 */
	protected $content;

	/**
	 * @var string
	 */
	protected $version;

	/**
	 * @var integer
	 */
	protected $statusCode;

	/**
	 * @var string
	 */
	protected $statusText;

	/**
	 * @var string
	 */
	protected $charset;

	/**
	 * Status codes translation table.
	 *
	 * The list of codes is complete according to the
	 * {@link http://www.iana.org/assignments/http-status-codes/ Hypertext Transfer Protocol (HTTP) Status Code Registry}
	 * (last updated 2012-02-13).
	 *
	 * Unless otherwise noted, the status code is defined in RFC2616.
	 *
	 * @var array
	 */
	public static $statusTexts = [
		100 => 'Continue',
		101 => 'Switching Protocols',
		102 => 'Processing',
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		207 => 'Multi-Status',
		208 => 'Already Reported',
		226 => 'IM Used',
		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		306 => 'Reserved',
		307 => 'Temporary Redirect',
		308 => 'Permanent Redirect',
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
		414 => 'Request-URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Requested Range Not Satisfiable',
		417 => 'Expectation Failed',
		418 => 'I\'m a teapot',
		422 => 'Unprocessable Entity',
		423 => 'Locked',
		424 => 'Failed Dependency',
		425 => 'Reserved for WebDAV advanced collections expired proposal',
		426 => 'Upgrade Required',
		428 => 'Precondition Required',
		429 => 'Too Many Requests',
		431 => 'Request Header Fields Too Large',
		451	=> 'Unavailable For Legal Reasons',
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported',
		506 => 'Variant Also Negotiates (Experimental)',
		507 => 'Insufficient Storage',
		508 => 'Loop Detected',
		510 => 'Not Extended',
		511 => 'Network Authentication Required',
	];

	/**
	 * Constructor.
	 *
	 * @param string  $content The response content
	 * @param integer $status  The response status code
	 * @param array   $headers An array of response headers
	 *
	 * @throws \InvalidArgumentException When the HTTP status code is not valid
	 */
	public function __construct($content = '', $status = 200, $headers = []) {

		$this->headers = new ResponseHeaderBag($headers);
		$this->setContent($content);
		$this->setStatusCode($status);
		$this->setProtocolVersion('1.0');

	}

	/**
	 * Factory method for chainability
	 *
	 * Example:
	 *
	 *     return Response::create($body, 200)
	 *         ->setSharedMaxAge(300);
	 *
	 * @param string  $content The response content
	 * @param integer $status  The response status code
	 * @param array   $headers An array of response headers
	 *
	 * @return Response
	 */
	public static function create($content = '', $status = 200, $headers = []) {

		return new static($content, $status, $headers);

	}

	/**
	 * Returns the Response as an HTTP string.
	 *
	 * The string representation of the Response is the same as the
	 * one that will be sent to the client only if the prepare() method
	 * has been called before.
	 *
	 * @return string The Response as an HTTP string
	 *
	 * @see prepare()
	 */
	public function __toString() {

		return
			sprintf('HTTP/%s %s %s', $this->version, $this->statusCode, $this->statusText) . "\r\n" .
			$this->headers . "\r\n" .
			$this->getContent();

	}

	/**
	 * Clones the current Response instance.
	 */
	public function __clone() {

		$this->headers = clone $this->headers;

	}

	/**
	 * Prepares the Response before it is sent to the client.
	 *
	 * This method tweaks the Response to ensure that it is
	 * compliant with RFC 2616. Most of the changes are based on
	 * the Request that is "associated" with this Response.
	 *
	 * @param Request $request A Request instance
	 *
	 * @return Response The current response.
	 */
	public function prepare(Request $request) {

		$headers = $this->headers;

		if ($this->isInformational() || $this->isEmpty()) {
			$this->setContent(NULL);
			$headers->remove('Content-Type');
			$headers->remove('Content-Length');
        }
        else {

			// Content-type based on the Request

        	if (!$headers->has('Content-Type')) {
				$format = $request->getRequestFormat();
				if (NULL !== $format && $mimeType = $request->getMimeType($format)) {
					$headers->set('Content-Type', $mimeType);
				}
			}

			// Fix Content-Type
			
			$charset = $this->charset ?: 'UTF-8';

			if (!$headers->has('Content-Type')) {
				$headers->set('Content-Type', 'text/html; charset='.$charset);
            }
			elseif (0 === stripos($headers->get('Content-Type'), 'text/') && FALSE === stripos($headers->get('Content-Type'), 'charset')) {

				// add the charset

				$headers->set('Content-Type', $headers->get('Content-Type') . '; charset=' . $charset);
			}

			// Fix Content-Length

			if ($headers->has('Transfer-Encoding')) {
				$headers->remove('Content-Length');
			}

			if ($request->isMethod('HEAD')) {

				$length = $headers->get('Content-Length');
                $this->setContent(NULL);

				if ($length) {
					$headers->set('Content-Length', $length);
				}
			}
		}

		// Fix protocol
		
		if ('HTTP/1.0' != $request->server->get('SERVER_PROTOCOL')) {
			$this->setProtocolVersion('1.1');
		}

		// Check if we need to send extra expire info headers

		if ('1.0' == $this->getProtocolVersion() && 'no-cache' == $this->headers->get('Cache-Control')) {
			$this->headers->set('pragma', 'no-cache');
			$this->headers->set('expires', -1);
		}

		$this->ensureIEOverSSLCompatibility($request);

		return $this;

	}

	/**
	 * Sends HTTP headers.
	 *
	 * @return Response
	 */
	public function sendHeaders() {

		// headers have already been sent by the developer

		if (headers_sent()) {
			return $this;
		}

		if (!$this->headers->has('Date')) {
			$this->setDate(\DateTime::createFromFormat('U', time()));
		}
		
		// headers

		foreach ($this->headers->allPreserveCase() as $name => $values) {
			foreach ($values as $value) {
				header($name . ': ' . $value, FALSE);
			}
		}

		// status

		header(sprintf('HTTP/%s %s %s', $this->version, $this->statusCode, $this->statusText), TRUE, $this->statusCode);

		// cookies
		foreach ($this->headers->getCookies() as $cookie) {
			setcookie($cookie->getName(), $cookie->getValue(), $cookie->getExpiresTime(), $cookie->getPath(), $cookie->getDomain(), $cookie->isSecure(), $cookie->isHttpOnly());
		}

		return $this;

	}

	/**
	 * Sends content for the current web response.
	 *
	 * @return Response
	 */
	public function sendContent() {

		echo $this->content;

		return $this;

	}

	/**
	 * Sends HTTP headers and content.
	 *
	 * @return Response
	 */
	public function send() {

		$this->sendHeaders();
		$this->sendContent();

		if (function_exists('fastcgi_finish_request')) {
			fastcgi_finish_request();
		}
		elseif ('cli' !== PHP_SAPI) {
			static::closeOutputBuffers(0, TRUE);
		}

		return $this;

	}

	/**
	 * Sets the response content.
	 *
	 * Valid types are strings, numbers, and objects that implement a __toString() method.
	 *
	 * @param mixed $content
	 *
	 * @return Response
	 *
	 * @throws \UnexpectedValueException
	 */
	public function setContent($content) {

		if (NULL !== $content && !is_string($content) && !is_numeric($content) && !is_callable(array($content, '__toString'))) {
			throw new \UnexpectedValueException(sprintf('The Response content must be a string or object implementing __toString(), "%s" given.', gettype($content)));
		}

		$this->content = (string) $content;

		return $this;

	}

	/**
	 * Gets the current response content.
	 *
	 * @return string Content
	 */
	public function getContent() {

		return $this->content;

	}

	/**
	 * Sets the HTTP protocol version (1.0 or 1.1).
	 *
	 * @param string $version The HTTP protocol version
	 *
	 * @return Response
	 */
	public function setProtocolVersion($version) {

		$this->version = $version;
		return $this;

	}

	/**
	 * Gets the HTTP protocol version.
	 *
	 * @return string The HTTP protocol version
	 */
	public function getProtocolVersion() {

		return $this->version;

	}

	/**
	 * Sets the response status code.
	 *
	 * @param integer $code HTTP status code
	 * @param mixed   $text HTTP status text
	 *
	 * If the status text is null it will be automatically populated for the known
	 * status codes and left empty otherwise.
	 *
	 * @return Response
	 *
	 * @throws \InvalidArgumentException When the HTTP status code is not valid
	 */
	public function setStatusCode($code, $text = NULL) {

		$this->statusCode = $code = (int) $code;

		if ($this->isInvalid()) {
			throw new \InvalidArgumentException(sprintf('The HTTP status code "%s" is not valid.', $code));
		}

		if (NULL === $text) {
			$this->statusText = isset(self::$statusTexts[$code]) ? self::$statusTexts[$code] : 'undefined status';

			return $this;
		}

		$this->statusText = $text;
		return $this;

	}

	/**
	 * Retrieves the status code for the current web response.
	 *
	 * @return integer Status code
	 */
	public function getStatusCode() {

		return $this->statusCode;

	}

	/**
	 * Sets the response charset.
	 *
	 * @param string $charset Character set
	 *
	 * @return Response
	 */
	public function setCharset($charset) {

		$this->charset = $charset;
		return $this;

	}

	/**
	 * Retrieves the response charset.
	 *
	 * @return string Character set
	 */
	public function getCharset() {

		return $this->charset;

	}

	/**
	 * Returns true if the response is worth caching under any circumstance.
	 *
	 * Responses marked "private" with an explicit Cache-Control directive are
	 * considered uncacheable.
	 *
	 * Responses with neither a freshness lifetime (Expires, max-age) nor cache
	 * validator (Last-Modified, ETag) are considered uncacheable.
	 *
	 * @return Boolean true if the response is worth caching, false otherwise
	 */
	public function isCacheable() {

		if (!in_array(
				$this->statusCode,
				[
					self::HTTP_OK,
					self::HTTP_NON_AUTHORITATIVE_INFORMATION,
					self::HTTP_MULTIPLE_CHOICES,
					self::HTTP_MOVED_PERMANENTLY,
					self::HTTP_FOUND,
					self::HTTP_NOT_FOUND,
					self::HTTP_GONE
				]
		)) {
			return FALSE;
		}

		if ($this->headers->hasCacheControlDirective('no-store') || $this->headers->getCacheControlDirective('private')) {
			return FALSE;
		}

		return $this->isValidateable() || $this->isFresh();

	}

	/**
	 * Returns true if the response is "fresh".
	 *
	 * Fresh responses may be served from cache without any interaction with the
	 * origin. A response is considered fresh when it includes a Cache-Control/max-age
	 * indicator or Expires header and the calculated age is less than the freshness lifetime.
	 *
	 * @return Boolean true if the response is fresh, false otherwise
	 */
	public function isFresh() {

		return $this->getTtl() > 0;

	}

	/**
	 * Returns true if the response includes headers that can be used to validate
	 * the response with the origin server using a conditional GET request.
	 *
	 * @return Boolean true if the response is validateable, false otherwise
	 */
	public function isValidateable() {

		return $this->headers->has('Last-Modified') || $this->headers->has('ETag');

	}

	/**
	 * Marks the response as "private".
	 *
	 * It makes the response ineligible for serving other clients.
	 *
	 * @return Response
	 */
	public function setPrivate() {

		$this->headers->removeCacheControlDirective('public');
		$this->headers->addCacheControlDirective('private');
		return $this;

	}

	/**
	 * Marks the response as "public".
	 *
	 * It makes the response eligible for serving other clients.
	 *
	 * @return Response
	 */
	public function setPublic() {

		$this->headers->addCacheControlDirective('public');
		$this->headers->removeCacheControlDirective('private');
		return $this;

	}

	/**
	 * Returns true if the response must be revalidated by caches.
	 *
	 * This method indicates that the response must not be served stale by a
	 * cache in any circumstance without first revalidating with the origin.
	 * When present, the TTL of the response should not be overridden to be
	 * greater than the value provided by the origin.
	 *
	 * @return Boolean true if the response must be revalidated by a cache, false otherwise
	 */
	public function mustRevalidate() {

		return $this->headers->hasCacheControlDirective('must-revalidate') || $this->headers->hasCacheControlDirective('proxy-revalidate');

	}

	/**
	 * Returns the Date header as a DateTime instance.
	 *
	 * @return \DateTime
	 * @throws \RuntimeException When the header is not parseable
	 */
	public function getDate() {

		if (!$this->headers->has('Date')) {
			$this->setDate(\DateTime::createFromFormat('U', time()));
		}
		return $this->headers->getDate('Date');
		
	}

	/**
	 * Sets the Date header.
	 *
	 * @param \DateTime $date
	 * @return Response
	 */
	public function setDate(\DateTime $date) {

		$date->setTimezone(new \DateTimeZone('UTC'));
		$this->headers->set('Date', $date->format('D, d M Y H:i:s') . ' GMT');

		return $this;

	}

	/**
	 * Returns the age of the response.
	 *
	 * @return integer The age of the response in seconds
	 */
	public function getAge() {

		if (NULL !== $age = $this->headers->get('Age')) {
			return (int) $age;
		}

		return max(time() - $this->getDate()->format('U'), 0);

	}

	/**
	 * Marks the response stale by setting the Age header to be equal to the maximum age of the response.
	 *
	 * @return Response
	 */
	public function expire() {

		if ($this->isFresh()) {
			$this->headers->set('Age', $this->getMaxAge());
		}
		return $this;

	}

	/**
	 * Returns the value of the Expires header as a DateTime instance.
	 *
	 * @return \DateTime|null A DateTime instance or null if the header does not exist
	 */
	public function getExpires() {

		try {
			return $this->headers->getDate('Expires');
		}
		catch (\RuntimeException $e) {

			// according to RFC 2616 invalid date formats (e.g. "0" and "-1") must be treated as in the past

			return \DateTime::createFromFormat(DATE_RFC2822, 'Sat, 01 Jan 00 00:00:00 +0000');
		}

	}

	/**
	 * Sets the Expires HTTP header with a DateTime instance.
	 * Passing null as value will remove the header.
	 *
	 * @param \DateTime|null $date A \DateTime instance or null to remove the header
	 * @return Response
	 */
	public function setExpires(\DateTime $date = NULL) {

		if (NULL === $date) {
			$this->headers->remove('Expires');
		}
		else {
			$date = clone $date;
			$date->setTimezone(new \DateTimeZone('UTC'));
			$this->headers->set('Expires', $date->format('D, d M Y H:i:s') . ' GMT');
		}

		return $this;

	}

	/**
	 * Returns the number of seconds after the time specified in the response's Date
	 * header when the response should no longer be considered fresh.
	 * First, it checks for a s-maxage directive, then a max-age directive, and then it falls
	 * back on an expires header. It returns null when no maximum age can be established.
	 *
	 * @return integer|null Number of seconds
	 */
	public function getMaxAge() {

		if ($this->headers->hasCacheControlDirective('s-maxage')) {
			return (int) $this->headers->getCacheControlDirective('s-maxage');
		}

		if ($this->headers->hasCacheControlDirective('max-age')) {
			return (int) $this->headers->getCacheControlDirective('max-age');
		}

		if (NULL !== $this->getExpires()) {
			return $this->getExpires()->format('U') - $this->getDate()->format('U');
		}

		return NULL;

	}

	/**
	 * Sets the number of seconds after which the response should no longer be considered fresh.
	 * This methods sets the Cache-Control max-age directive.
	 *
	 * @param integer $value Number of seconds
	 * @return Response
	 */
	public function setMaxAge($value) {

		$this->headers->addCacheControlDirective('max-age', $value);
		return $this;

	}

	/**
	 * Sets the number of seconds after which the response should no longer be considered fresh by shared caches.
	 * This methods sets the Cache-Control s-maxage directive.
	 *
	 * @param integer $value Number of seconds
	 * @return Response
	 */
	public function setSharedMaxAge($value) {

		$this->setPublic();
		$this->headers->addCacheControlDirective('s-maxage', $value);
		return $this;

	}

	/**
	 * Returns the response's time-to-live in seconds.
	 * It returns null when no freshness information is present in the response.
	 * When the responses TTL is <= 0, the response may not be served from cache without first
	 * revalidating with the origin.
	 *
	 * @return integer|null The TTL in seconds
	 */
	public function getTtl() {

		if (NULL !== $maxAge = $this->getMaxAge()) {
			return $maxAge - $this->getAge();
		}

	}

	/**
	 * Sets the response's time-to-live for shared caches.
	 * This method adjusts the Cache-Control/s-maxage directive.
	 *
	 * @param integer $seconds Number of seconds
	 * @return Response
	 */
	public function setTtl($seconds) {

		$this->setSharedMaxAge($this->getAge() + $seconds);
		return $this;

	}

	/**
	 * Sets the response's time-to-live for private/client caches.
	 * This method adjusts the Cache-Control/max-age directive.
	 *
	 * @param integer $seconds Number of seconds
	 * @return Response
	 */
	public function setClientTtl($seconds) {

		$this->setMaxAge($this->getAge() + $seconds);
		return $this;

	}

	/**
	 * Returns the Last-Modified HTTP header as a DateTime instance.
	 *
	 * @return \DateTime|null A DateTime instance or null if the header does not exist
	 * @throws \RuntimeException When the HTTP header is not parseable
	 */
	public function getLastModified() {

		return $this->headers->getDate('Last-Modified');

	}

	/**
	 * Sets the Last-Modified HTTP header with a DateTime instance.
	 * Passing null as value will remove the header.
	 *
	 * @param \DateTime|null $date A \DateTime instance or null to remove the header
	 * @return Response
	 */
	public function setLastModified(\DateTime $date = NULL) {

		if (NULL === $date) {
			$this->headers->remove('Last-Modified');
		}
		else {
			$date = clone $date;
			$date->setTimezone(new \DateTimeZone('UTC'));
			$this->headers->set('Last-Modified', $date->format('D, d M Y H:i:s') . ' GMT');
		}

		return $this;

	}

	/**
	 * Returns the literal value of the ETag HTTP header.
	 *
	 * @return string|null The ETag HTTP header or null if it does not exist
	 */
	public function getEtag() {

		return $this->headers->get('ETag');

	}

	/**
	 * Sets the ETag value.
	 *
	 * @param string|null $etag The ETag unique identifier or null to remove the header
	 * @param Boolean     $weak Whether you want a weak ETag or not
	 * @return Response
	 */
	public function setEtag($etag = NULL, $weak = FALSE) {

		if (NULL === $etag) {
			$this->headers->remove('Etag');
		}
		else {

			if (0 !== strpos($etag, '"')) {
				$etag = '"' . $etag . '"';
			}

			$this->headers->set('ETag', (TRUE === $weak ? 'W/' : '') . $etag);
		}

		return $this;

	}

	/**
	 * Sets the response's cache headers (validation and/or expiration).
	 * Available options are: etag, last_modified, max_age, s_maxage, private, and public.
	 *
	 * @param array $options An array of cache options
	 * @return Response
	 *
	 * @throws \InvalidArgumentException
	 */
	public function setCache(array $options) {

		if ($diff = array_diff(array_keys($options), ['etag', 'last_modified', 'max_age', 's_maxage', 'private', 'public'])) {
			throw new \InvalidArgumentException(sprintf('Response does not support the following options: "%s".', implode('", "', array_values($diff))));
		}

		if (isset($options['etag'])) {
			$this->setEtag($options['etag']);
		}
		if (isset($options['last_modified'])) {
			$this->setLastModified($options['last_modified']);
		}
		if (isset($options['max_age'])) {
			$this->setMaxAge($options['max_age']);
		}
		if (isset($options['s_maxage'])) {
			$this->setSharedMaxAge($options['s_maxage']);
		}
		if (isset($options['public'])) {
			if ($options['public']) {
				$this->setPublic();
			}
			else {
				$this->setPrivate();
			}
		}

		if (isset($options['private'])) {
			if ($options['private']) {
				$this->setPrivate();
			}
			else {
				$this->setPublic();
			}
		}

		return $this;

	}

	/**
	 * Modifies the response so that it conforms to the rules defined for a 304 status code.
	 * This sets the status, removes the body, and discards any headers
	 * that MUST NOT be included in 304 responses.
	 *
	 * @return Response
	 *
	 * @see http://tools.ietf.org/html/rfc2616#section-10.3.5
	 */
	public function setNotModified() {

		$this->setStatusCode(self::HTTP_NOT_MODIFIED);
		$this->setContent(NULL);

		// remove headers that MUST NOT be included with 304 Not Modified responses

		foreach (['Allow', 'Content-Encoding', 'Content-Language', 'Content-Length', 'Content-MD5', 'Content-Type', 'Last-Modified'] as $header) {
			$this->headers->remove($header);
		}

		return $this;

	}

	/**
	 * Returns true if the response includes a Vary header.
	 *
	 * @return Boolean true if the response includes a Vary header, false otherwise
	 */
	public function hasVary() {

		return NULL !== $this->headers->get('Vary');

	}

	/**
	 * Returns an array of header names given in the Vary header.
	 *
	 * @return array An array of Vary names
	 */
	public function getVary() {

		if (!$vary = $this->headers->get('Vary', NULL, FALSE)) {
			return [];
		}
		
		$ret = [];
		foreach ($vary as $item) {
			$ret = array_merge($ret, preg_split('/[\s,]+/', $item));
		}
		
		return $ret;

	}

	/**
	 * Sets the Vary header.
	 *
	 * @param string|array $headers
	 * @param Boolean      $replace Whether to replace the actual value of not (true by default)
	 * @return Response
	 */
	public function setVary($headers, $replace = TRUE) {

		$this->headers->set('Vary', $headers, $replace);
		return $this;

	}

	/**
	 * Determines if the Response validators (ETag, Last-Modified) match
	 * a conditional value specified in the Request.
	 * If the Response is not modified, it sets the status code to 304 and
	 * removes the actual content by calling the setNotModified() method.
	 *
	 * @param Request $request A Request instance
	 * @return Boolean true if the Response validators match the Request, false otherwise
	 */
	public function isNotModified(Request $request) {

		if (!$request->isMethodSafe()) {
			return FALSE;
		}

		$lastModified	= $request->headers->get('If-Modified-Since');
		$modifiedSince	= $request->headers->get('If-Modified-Since');
		$notModified	= FALSE;

		if ($etags = $request->getETags()) {
			$notModified = in_array($this->getEtag(), $etags) || in_array('*', $etags);
        }
		if ($modifiedSince && $lastModified) {
			$notModified = strtotime($modifiedSince) >= strtotime($lastModified) && (!$etags || $notModified);
		}
		if ($notModified) {
			$this->setNotModified();
		}

		return $notModified;

	}

	/**
	 * Is response invalid?
	 * 
	 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
	 * @return Boolean
	 */
	public function isInvalid() {

		return $this->statusCode < 100 || $this->statusCode >= 600;

	}

	/**
	 * Is response informative?
	 *
	 * @return Boolean
	 */
	public function isInformational() {

		return substr((string) $this->statusCode, 0, 1) === '1';

	}

	/**
	 * Is response successful?
	 *
	 * @return Boolean
	 */
	public function isSuccessful() {

		return substr((string) $this->statusCode, 0, 1) === '2';

	}

	/**
	 * Is the response a redirect?
	 *
	 * @return Boolean
	 */
	public function isRedirection() {

		return substr((string) $this->statusCode, 0, 1) === '3';

	}

	/**
	 * Is there a client error?
	 *
	 * @return Boolean
	 */
	public function isClientError() {

		return substr((string) $this->statusCode, 0, 1) === '4';
	
	}

	/**
	 * Was there a server side error?
	 *
	 * @return Boolean
	 */
	public function isServerError() {

		return substr((string) $this->statusCode, 0, 1) === '5';

	}

	/**
	 * Is the response OK?
	 *
	 * @return Boolean
	 */
	public function isOk() {

		return self::HTTP_OK === $this->statusCode;

	}

	/**
	 * Is the response forbidden?
	 *
	 * @return Boolean
	 */
	public function isForbidden() {

		return self::HTTP_FORBIDDEN === $this->statusCode;

	}

	/**
	 * Is the response a not found error?
	 *
	 * @return Boolean
	 */
	public function isNotFound() {

		return self::HTTP_NOT_FOUND === $this->statusCode;

	}

	/**
	 * Is the response a redirect of some form?
	 * 
	 * @param string $location
	 * @return Boolean
	 */
	public function isRedirect($location = NULL) {

		return in_array(
				$this->statusCode,
				[
					self::HTTP_CREATED,
					self::HTTP_MOVED_PERMANENTLY,
					self::HTTP_FOUND,
					self::HTTP_SEE_OTHER,
					self::HTTP_TEMPORARY_REDIRECT,
					self::HTTP_PERMANENTLY_REDIRECT
				]
		) && (NULL === $location ?: $location == $this->headers->get('Location'));

	}

	/**
	 * Is the response empty?
	 *
	 * @return Boolean
	 */
	public function isEmpty() {

		return in_array($this->statusCode, [self::HTTP_NO_CONTENT, self::HTTP_NOT_MODIFIED]);

	}

	/**
	 * Cleans or flushes output buffers up to target level.
	 * Resulting level can be greater than target level if a non-removable buffer has been encountered.
	 *
	 * @param int  $targetLevel The target output buffering level
	 * @param bool $flush       Whether to flush or clean the buffers
	 */
	public static function closeOutputBuffers($targetLevel, $flush) {

		$status	= ob_get_status(TRUE);
		$level	= count($status);

		// PHP_OUTPUT_HANDLER_* are not defined on HHVM 3.3

		$flags = defined('PHP_OUTPUT_HANDLER_REMOVABLE') ? PHP_OUTPUT_HANDLER_REMOVABLE | ($flush ? PHP_OUTPUT_HANDLER_FLUSHABLE : PHP_OUTPUT_HANDLER_CLEANABLE) : -1;
	
		while ($level-- > $targetLevel && ($s = $status[$level]) && (!isset($s['del']) ? !isset($s['flags']) || $flags === ($s['flags'] & $flags) : $s['del'])) {
			if ($flush) {
				ob_end_flush();
			}
			else {
				ob_end_clean();
			}
		}

	}
	
	/**
	 * Check if we need to remove Cache-Control for ssl encrypted downloads when using IE < 9
	 *
	 * @link http://support.microsoft.com/kb/323308
	 */
	protected function ensureIEOverSSLCompatibility(Request $request) {

		if (
			FALSE !== stripos($this->headers->get('Content-Disposition'), 'attachment') &&
			preg_match('/MSIE (.*?);/i', $request->server->get('HTTP_USER_AGENT'), $match) == 1 &&
			TRUE === $request->isSecure()
		) {
			if (intval(preg_replace('/(MSIE )(.*?);/', '$2', $match[0])) < 9) {
				$this->headers->remove('Cache-Control');
			}
		}

	}
}

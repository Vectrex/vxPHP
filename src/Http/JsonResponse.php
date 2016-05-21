<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace vxPHP\Http;

/**
 * Response represents an HTTP response in JSON format.
 *
 * Note that this class does not force the returned JSON content to be an
 * object. It is however recommended that you do return an object as it
 * protects yourself against XSSI and JSON-JavaScript Hijacking.
 *
 * @see https://www.owasp.org/index.php/OWASP_AJAX_Security_Guidelines#Always_return_JSON_with_an_Object_on_the_outside
 *
 * @version 0.2.0
 * @author Igor Wiedler <igor@wiedler.ch>, Gregor Kofler
 */
class JsonResponse extends Response {

	protected $data;
	protected $callback;
	protected $encodingOptions;

	/**
	 * constructor
	 *
	 * @param mixed $data
	 * @param integer $statusCode
	 * @param array   $headers
	 */
	public function __construct($responseData = NULL, $statusCode = 200, $headers = []) {

		parent::__construct('', $statusCode, $headers);

		if(is_null($responseData)) {
			$responseData = new \ArrayObject();
		}

		// encode <, >, ', &, and " for RFC4627-compliant JSON, which may also be embedded into HTML

		$this->setEncodingOptions(JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
		$this->setPayload($responseData);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function create($responseData = NULL, $statusCode = 200, $headers = []) {

		return new static($responseData, $statusCode, $headers);

	}
	
	/**
	 * set the JSONP callback
	 * pass NULL for not using a callback
	 *
	 * @param string|null $callback
	 *
	 * @return JsonResponse
	 *
	 * @throws \InvalidArgumentException when callback name is not valid
	 */
	public function setCallback($callback = NULL) {

		if (NULL !== $callback) {

			// taken from http://www.geekality.net/2011/08/03/valid-javascript-identifier/
			$pattern = '/^[$_\p{L}][$_\p{L}\p{Mn}\p{Mc}\p{Nd}\p{Pc}\x{200C}\x{200D}]*+$/u';

			foreach (explode('.', $callback) as $part) {
				if (!preg_match($pattern, $part)) {
					throw new \InvalidArgumentException(sprintf("The callback name '%s' is not valid.", $part));
				}
			}
		}

		$this->callback = $callback;

		return $this->update();

	}
	
	/**
	 * Set payload
	 *
	 * @param mixed $responseData
	 *
	 * @return JsonResponse
	 */
	public function setPayload($responseData) {

		try {
			$this->data = json_encode($responseData, $this->encodingOptions);
		}

		catch (\Exception $e) {

			// PHP 5.4 wrap exceptions thrown by JsonSerializable in a new exception that needs to be removed

			if ('Exception' === get_class($e) && 0 === strpos($e->getMessage(), 'Failed calling ')) {
				throw $e->getPrevious() ?: $e;
			}
			throw $e;
		}

		if (JSON_ERROR_NONE !== json_last_error()) {
			throw new \InvalidArgumentException(json_last_error_msg());
		}

		return $this->update();

	}

	/**
	 * get options used when encoding data to JSON
	 * 
	 * @return int
	 */
	public function getEncodingOptions() {

		return $this->encodingOptions;

	}

	/**
	 * set options used while encoding data to JSON
	 * re-encodes payload with new encoding setting
	 * 
	 * @param int $encodingOptions
	 * 
	 * @return JsonResponse
	 */
	public function setEncodingOptions($encodingOptions) {

		$this->encodingOptions = (int) $encodingOptions;
		
		return $this->setPayload(json_decode($this->data));

	}
	
	/**
	 * update content and headers according to the JSON data and a set callback
	 *
	 * @return JsonResponse
	 */
	protected function update() {
		
		if(!is_null($this->callback)) {
			
			// Not using application/javascript for compatibility reasons with older browsers.
			$this->headers->set('Content-Type', 'text/javascript');
				
			return $this->setContent(sprintf('/**/%s(%s);', $this->callback, $this->data)); 

		}
		
		// set header only when there is none or when it equals 'text/javascript' (from a previous update with callback)
		// in order to not overwrite a custom definition

		if(!$this->headers->has('Content-Type') || $this->headers->get('Content-Type') === 'text/javascript') {
			$this->headers->set('Content-Type', 'application/json');
		}

		return $this->setContent($this->data);

	}
	
}

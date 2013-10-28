<?php
/**
 * response in JSON format
 *
 * @version 0.1.1
 * @author Igor Wiedler <igor@wiedler.ch>, Gregor Kofler
 *
 */


namespace vxPHP\Http;

use vxPHP\Http\Response;

class JsonResponse extends Response {

	protected $data;

	/**
	 * cosntructor
	 *
	 * @param mixed $data
	 * @param integer $statusCode
	 * @param array   $headers
	 */
	public function __construct($responseData = NULL, $statusCode = 200, $headers = array()) {

		parent::__construct('', $statusCode, $headers);

		if(is_null($responseData)) {
			$responseData = new \stdClass();
		}

		$this->setPayload($responseData);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function create($responseData = array(), $statusCode = 200, $headers = array()) {
		return new static($responseData, $statusCode, $headers);
	}

	/**
	 * Set payload
	 *
	 * @param mixed $responseData
	 *
	 * @return JsonResponse
	 */
	public function setPayload($responseData) {

		// encode <, >, ', &, and " for RFC4627-compliant JSON, which may also be embedded into HTML

		$this->data = json_encode($responseData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
		return $this->update();
	}

	/**
	 * update content and headers according to the json data
	 *
	 * @return JsonResponse
	 */
	protected function update() {
		if(!$this->headers->has('Content-Type') || $this->headers->get('Content-Type') === 'text/javascript') {
			$this->headers->set('Content-Type', 'application/json');
		}

		return $this->setContent($this->data);
	}
}

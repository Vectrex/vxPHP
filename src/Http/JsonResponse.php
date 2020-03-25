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
class JsonResponse extends Response
{
    protected $data;

    // Encode <, >, ', &, and " characters in the JSON, making it also safe to be embedded into HTML.
    // 15 === JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    public const DEFAULT_ENCODING_OPTIONS = 15;

    protected $encodingOptions = self::DEFAULT_ENCODING_OPTIONS;

    /**
     * @param mixed $data The response data
     * @param int $status The response status code
     * @param array $headers An array of response headers
     * @param bool $json If the data is already a JSON string
     * @throws \Exception
     */
    public function __construct($data = null, int $status = 200, array $headers = [], bool $json = false)
    {
        parent::__construct('', $status, $headers);

        if (null === $data) {
            $data = new \ArrayObject();
        }

        $json ? $this->setJson($data) : $this->setData($data);
    }

    /**
     * Factory method for chainability.
     *
     * Example:
     *
     *     return JsonResponse::create(['key' => 'value'])
     *         ->setSharedMaxAge(300);
     *
     * @param mixed $data The JSON response data
     * @param int $status The response status code
     * @param array $headers An array of response headers
     *
     * @return static
     * @throws \Exception
     */
    public static function create($data = null, $status = 200, $headers = []): Response
    {
        return new static($data, $status, $headers);
    }

    /**
     * Factory method for chainability.
     *
     * Example:
     *
     *     return JsonResponse::fromJsonString('{"key": "value"}')
     *         ->setSharedMaxAge(300);
     *
     * @param string|null $data The JSON response string
     * @param int $status The response status code
     * @param array $headers An array of response headers
     *
     * @return static
     * @throws \Exception
     */
    public static function fromJsonString($data = null, $status = 200, $headers = []): self
    {
        return new static($data, $status, $headers, true);
    }

    /**
     * Sets a raw string containing a JSON document to be sent.
     *
     * @param string $json
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setJson($json): self
    {
        $this->data = $json;

        return $this->update();
    }

    /**
     * Sets the data to be sent as JSON.
     *
     * @param mixed $data
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function setData($data = []): self
    {
        try {
            $data = json_encode($data, $this->encodingOptions);
        } catch (\Exception $e) {
            if ('Exception' === \get_class($e) && 0 === strpos($e->getMessage(), 'Failed calling ')) {
                throw $e->getPrevious() ?: $e;
            }
            throw $e;
        }

        if (\PHP_VERSION_ID >= 70300 && (JSON_THROW_ON_ERROR & $this->encodingOptions)) {
            return $this->setJson($data);
        }

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \InvalidArgumentException(json_last_error_msg());
        }

        return $this->setJson($data);
    }

    /**
     * Returns options used while encoding data to JSON.
     *
     * @return int
     */
    public function getEncodingOptions(): int
    {
        return $this->encodingOptions;
    }

    /**
     * Sets options used while encoding data to JSON.
     *
     * @param int $encodingOptions
     *
     * @return $this
     * @throws \Exception
     */
    public function setEncodingOptions($encodingOptions): self
    {
        $this->encodingOptions = (int) $encodingOptions;

        return $this->setData(json_decode($this->data));
    }

    /**
     * Updates the content and headers according to the JSON data and callback.
     *
     * @return $this
     */
    protected function update(): self
    {
        // Only set the header when there is none
        // in order to not overwrite a custom definition.
        if (!$this->headers->has('Content-Type')) {
            $this->headers->set('Content-Type', 'application/json');
        }

        return $this->setContent($this->data);
    }
}

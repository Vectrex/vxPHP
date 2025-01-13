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
 * StreamedResponse represents a streamed HTTP response.
 *
 * A StreamedResponse uses a callback for its content.
 *
 * The callback should use the standard PHP functions like echo
 * to stream the response back to the client. The flush() function
 * can also be used if needed.
 *
 * @see flush()
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class StreamedResponse extends Response
{
    /**
     * @var callable|null
     */
    protected $callback;
    protected bool $streamed;
    private bool $headersSent;

    /**
     * @param callable|null $callback A valid PHP callback or null to set it later
     * @param int           $status   The response status code
     * @param array         $headers  An array of response headers
     */
    public function __construct(?callable $callback = null, int $status = 200, array $headers = [])
    {
        parent::__construct(null, $status, $headers);

        if (null !== $callback) {
            $this->setCallback($callback);
        }
        $this->streamed = false;
        $this->headersSent = false;
    }

    /**
     * Factory method for chainability.
     *
     * @param callable|null $callback A valid PHP callback or null to set it later
     * @param int           $status   The response status code
     * @param array         $headers  An array of response headers
     *
     * @return static
     */
    public static function create(mixed $callback = null, int $status = 200, array $headers = []): Response
    {
        return new static($callback, $status, $headers);
    }

    /**
     * Sets the PHP callback associated with this Response.
     *
     * @return $this
     */
    public function setCallback(callable $callback): self
    {
        $this->callback = $callback;
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * This method only sends the headers once.
     *
     * @return $this
     */
    public function sendHeaders(): Response
    {
        if ($this->headersSent) {
            return $this;
        }

        $this->headersSent = true;

        return parent::sendHeaders();
    }

    /**
     * {@inheritdoc}
     *
     * This method only sends the content once.
     *
     * @return $this
     */
    public function sendContent(): Response
    {
        if ($this->streamed) {
            return $this;
        }

        $this->streamed = true;

        if (null === $this->callback) {
            throw new \LogicException('The Response callback must not be null.');
        }

        ($this->callback)();

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \LogicException when the content is not null
     *
     * @return $this
     */
    public function setContent($content): Response
    {
        if (null !== $content) {
            throw new \LogicException('The content cannot be set on a StreamedResponse instance.');
        }

        $this->streamed = true;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(): false
    {
        return false;
    }
}

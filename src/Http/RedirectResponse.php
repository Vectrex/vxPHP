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
 * RedirectResponse represents an HTTP response doing a redirect
 *
 * @author Fabien Potencier <fabien@symfony.com>, Gregor Kofler
 */
class RedirectResponse extends Response
{
    protected ?string $targetUrl = null;

    /**
     * Creates a redirect response so that it conforms to the rules defined for a redirect status code.
     *
     * @param string $url     The URL to redirect to. The URL should be a full URL, with schema etc.,
     *                        but practically every browser redirects on paths only as well
     * @param int    $status  The status code (302 by default)
     * @param array  $headers The headers (Location is always set to the given URL)
     *
     * @throws \InvalidArgumentException
     *
     * @see https://tools.ietf.org/html/rfc2616#section-10.3
     */
    public function __construct(string $url, int $status = 302, array $headers = [])
    {
		parent::__construct('', $status, $headers);
	
		$this->setTargetUrl($url);
	
		if (!$this->isRedirect()) {
			throw new \InvalidArgumentException(sprintf('The HTTP status code is not a redirect ("%s" given).', $status));
		}

        if (301 === $status && !\array_key_exists('cache-control', array_change_key_case($headers, CASE_LOWER))) {
            $this->headers->remove('cache-control');
        }
    }

    /**
     * Factory method for chainability.
     *
     * @param string $url     The url to redirect to
     * @param int    $status  The response status code
     * @param array  $headers An array of response headers
     *
     * @return static
     */
    public static function create($url = '', int $status = 302, array $headers = []): Response
    {
        return new static($url, $status, $headers);
    }

    /**
     * Returns the target URL.
     *
     * @return string|null target URL
     */
    public function getTargetUrl(): ?string
    {
        return $this->targetUrl;
    }

    /**
     * Sets the redirect target of this response.
     *
     * @param string|null $url The URL to redirect to
     *
     * @return $this
     *
     */
    public function setTargetUrl(?string $url): self
    {
        if ('' === ($url ?? '')) {
            throw new \InvalidArgumentException('Cannot redirect to an empty URL.');
        }

        $this->targetUrl = $url;

        $this->setContent(
            sprintf('<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta http-equiv="refresh" content="0;url=\'%1$s\'" />

        <title>Redirecting to %1$s</title>
    </head>
    <body>
        Redirecting to <a href="%1$s">%1$s</a>.
    </body>
</html>', htmlspecialchars($url, ENT_QUOTES)));

        $this->headers->set('Location', $url);

        return $this;
    }
}
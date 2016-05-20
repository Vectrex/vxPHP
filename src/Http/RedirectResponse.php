<?php

namespace vxPHP\Http;

use vxPHP\Http\Response;

/**
 * RedirectResponse represents a HTTP response doing a redirect
 *
 * @author Fabien Potencier <fabien@symfony.com>, Gregor Kofler
 */
class RedirectResponse extends Response {
	
	private $targetUrl;
	
	/**
	 * creates a redirect response conforming to the rules defined for a redirect status code
	 * The URL to redirect to should be a full URL, with schema etc.
	 *
	 * @param string  $url
	 * @param integer $status
	 * @param array   $headers
	 * 
	 * @throws \InvalidArgumentException
	 */
	public function __construct($url, $status = Response::HTTP_FOUND, $headers = []) {

		parent::__construct('', $status, $headers);
	
		$this->setTargetUrl($url);
	
		if (!$this->isRedirect()) {
			throw new \InvalidArgumentException(sprintf('The HTTP status code is not a redirect ("%s" given).', $status));
		}

	}
	
	/**
	 * {@inheritDoc}
	 */
	public static function create($url = '', $status = Response::HTTP_FOUND, $headers = []) {

		return new static($url, $status, $headers);

	}
	
	/**
	 * get target URL
	 *
	 * @return string
	 */
	public function getTargetUrl() {

		return $this->targetUrl;

	}

	/**
	 * set redirect target of the response
	 *
	 * @param string  $url
	 *
	 * @return RedirectResponse
	 */
	public function setTargetUrl($url) {

		if (empty($url)) {
			throw new \InvalidArgumentException('Cannot redirect to an empty URL.');
		}

		$this->targetUrl = $url;

		$tpl = <<<'EOD'
<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta http-equiv="refresh" content="1;url=%1$s" />

		<title>Redirecting to %1$s</title>
	</head>
	<body>
		<p>Redirecting to <a href="%1$s">%1$s</a>.</p>
	</body>
</html>
EOD;
		$this->setContent(sprintf($tpl, htmlspecialchars($url, ENT_QUOTES, 'UTF-8')));
		$this->headers->set('Location', $url);

		return $this;
	}

}
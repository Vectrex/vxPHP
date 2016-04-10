<?php

namespace vxPHP\Security\Csrf;

use vxPHP\Session\SessionDataBag;
use vxPHP\Security\Csrf\Exception\CsrfTokenException;

/**
 * session storage for CSRF tokens
 * 
 * @author Gregor Kofler
 * @version 0.2.0 2016-04-06
 *
 */
class CsrfTokenSessionStorage {
	
	
	/**
	 * The namespace used to store values in the session.
	 *
	 * @var string
	 */
	const SESSION_NAMESPACE = '_csrf';
	
	/**
	 * the session data bag which is used to store the token
	 *
	 * @var SessionDataBag
	 */
	private $sessionDataBag;
	
	/**
	 * @var string
	 */
	private $namespace;
	
	/**
	 * constructor
	 * retrieve session data (and if needed initialize session along the way)
	 * and the namespace under which the token is stored
	 *
	 * @param SessionDataBag $sessionDataBag
	 * @param string $namespace
	 */
	public function __construct(SessionDataBag $sessionDataBag, $namespace = self::SESSION_NAMESPACE) {

		$this->sessionDataBag	= $sessionDataBag;
		$this->namespace		= $namespace;

	}

	/**
	 * retrieves token stored in session under namespaced token id
	 * 
	 * @param string $tokenId
	 * @return string
	 * 
	 * @throws CsrfTokenException
	 */
	public function getToken($tokenId) {

		$sessionToken = $this->sessionDataBag->get($this->namespace . '/' . $tokenId);

		if(!$sessionToken) {
			throw new CsrfTokenException(sprintf("CSRF token with id '%s' not found.", $tokenId));
		}

		return $sessionToken;
	}

	/**
	 * store token under namespaced token id in session
	 * 
	 * @param string $tokenId
	 * @param CsrfToken $token
	 * 
	 * @throws \InvalidArgumentException
	 */
	public function setToken($tokenId, CsrfToken $token) {
		
		if(!trim((string) $tokenId)) {
			throw new \InvalidArgumentException('Invalid token id.');
		}
		
		$this->sessionDataBag->set(
			$this->namespace . '/' . $tokenId,
			$token
		);
	}

	/**
	 * remove a token previously stored under a namespaced token id in session
	 *
	 * @param string $tokenId
	 */
	public function removeToken($tokenId) {
	
		return $this->sessionDataBag->remove($this->namespace . '/' . $tokenId);

	}
	
	
	/**
	 * check whether a token is stored under a namespaced token id in session
	 * 
	 * @param unknown $tokenId
	 */
	public function hasToken($tokenId) {
		
		return $this->sessionDataBag->has($this->namespace . '/' . $tokenId);

	}

}
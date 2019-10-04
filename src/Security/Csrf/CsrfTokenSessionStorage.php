<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


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
	public const SESSION_NAMESPACE = '_csrf';
	
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
	public function __construct(SessionDataBag $sessionDataBag, $namespace = self::SESSION_NAMESPACE)
    {
		$this->sessionDataBag = $sessionDataBag;
		$this->namespace = $namespace;
	}

	/**
	 * retrieves token stored in session under namespaced token id
	 * 
	 * @param string $tokenId
	 * @return CsrfToken
	 * 
	 * @throws CsrfTokenException
	 */
	public function getToken($tokenId): CsrfToken
    {
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
	public function setToken($tokenId, CsrfToken $token): void
    {
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
     * @return mixed
     */
	public function removeToken($tokenId): ?CsrfToken
    {
		return $this->sessionDataBag->remove($this->namespace . '/' . $tokenId);
	}

    /**
     * check whether a token is stored under a namespaced token id in session
     *
     * @param string $tokenId
     * @return bool
     */
	public function hasToken($tokenId): bool
    {
		return $this->sessionDataBag->has($this->namespace . '/' . $tokenId);
	}
}
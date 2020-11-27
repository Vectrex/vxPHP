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

use vxPHP\Session\Session;

/**
 * simple wrapper for CSRF token management
 *  
 * @author Gregor Kofler
 * @version 0.4.0 2020-11-27
 */
class CsrfTokenManager
{

	/**
	 * @var CsrfTokenSessionStorage
	 */
	private $storage;
	
	private $tokenLength;

	/**
	 * create a CSRF provider by combining session storage with token generation
	 * when generating new random value for a token
	 * random bytes of $tokenLength are created
	 * 
	 * @param integer $tokenLength
	 */
	public function __construct($tokenLength = 32)
    {
		$this->storage = new CsrfTokenSessionStorage(Session::getSessionDataBag());
		$this->tokenLength = (int) $tokenLength;
	}

    /**
     * returns a CSRF token for the given token id
     *
     * if previously no token existed for the given id, a new token is
     * generated, stored and returned; otherwise the existing token is returned
     *
     * @param string $tokenId
     * @return CsrfToken
     * @throws Exception\CsrfTokenException
     */
	public function getToken(string $tokenId): CsrfToken
    {
		if($this->storage->hasToken($tokenId)) {
			return $this->storage->getToken($tokenId);
		}

		$token = new CsrfToken(
			$tokenId,
			$this->generateValue((int) $this->tokenLength)
		);

		$this->storage->setToken($tokenId, $token);

		return $token;
	}

    /**
     * generates a new token for the given id
     * a new token will be generated, independent
     * of whether a token value previously existed or not
     * useful to enforce once-only tokens
     *
     * @param string $tokenId
     * @return CsrfToken
     * @throws \Exception
     */
	public function refreshToken(string $tokenId): CsrfToken
    {
		$this->storage->removeToken($tokenId);

		$token = new CsrfToken(
			$tokenId,
			$this->generateValue((int) $this->tokenLength)
		);

		$this->storage->setToken($tokenId, $token);

		return $token;
	}
	
    /**
	 * removes the CSRF token with the given id
	 * no further checking is done, whether the token previously existed
	 * returns NULL when token did not exist, otherwise the token
	 *
	 * @param string $tokenId
     * @return CsrfToken
     */
	public function removeToken(string $tokenId): ?CsrfToken
    {
		return $this->storage->removeToken($tokenId);
	}

    /**
     * check whether the given CSRF token is valid
     * returns TRUE if the token is valid, FALSE otherwise
     *
     * @param CsrfToken $token
     * @return bool
     * @throws Exception\CsrfTokenException
     */
	public function isTokenValid(CsrfToken $token): bool
    {
		$tokenId = $token->getId();

		if (!$this->storage->hasToken($tokenId)) {
			return false;
		}
	
		return hash_equals($this->storage->getToken($tokenId)->getValue(), $token->getValue());
	}

    /**
     * generate random bytes of length $length
     * use random_bytes() when available
     *
     * @param int $length
     * @return string
     * @throws \InvalidArgumentException
     * @throws \BadFunctionCallException
     * @throws \Exception
     */
	protected function generateValue(int $length = 16): string
    {
		return rtrim(strtr(base64_encode(random_bytes($length)), '+/', '-_'), '=');
	}
}
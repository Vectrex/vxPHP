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
 * polyfill for hash_equals() by Cedric Van Bockhaven
 * @link http://php.net/manual/de/function.hash-equals.php
 */
if(!function_exists('hash_equals')) {

	function hash_equals($a, $b) {

		$ret = strlen($a) ^ strlen($b);
		$ret |= array_sum(unpack('C*', $a ^ $b));
		return !$ret;

	}
}

/**
 * simple wrapper for CSRF token management
 *  
 * @author Gregor Kofler
 * @version 0.3.1 2017-05-04
 */
class CsrfTokenManager {

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
	public function __construct($tokenLength = 32) {

		$this->storage		= new CsrfTokenSessionStorage(Session::getSessionDataBag());
		$this->tokenLength	= (int) $tokenLength;
		
	}
	
	/**
	 * returns a CSRF token for the given token id
	 * 
	 * if previously no token existed for the given id, a new token is
	 * generated, stored and returned; otherwise the existing token is returned
	 * 
	 * @param string $tokenId
	 * @return CsrfToken
	 */
	public function getToken($tokenId) {

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
     */
	public function refreshToken($tokenId) {

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
	public function removeToken($tokenId) {

		return $this->storage->removeToken($tokenId);

	}
	
	/**
	 * check whether the given CSRF token is valid
	 * returns TRUE if the token is valid, FALSE otherwise
	 * 
	 * @param CsrfToken $token
	 * @return bool
	 */
	public function isTokenValid(CsrfToken $token) {

		$tokenId = $token->getId();

		if (!$this->storage->hasToken($tokenId)) {
			return FALSE;
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
	 * @throws \Exception
	 */
	protected function generateValue($length) {
	
		if(!(int) $length) {
			throw new \InvalidArgumentException('CSRF token length not set or too short.');
		}
	
		if (function_exists('random_bytes')) {
			$randomBytes = random_bytes($length);
		}
	
		else if (function_exists('mcrypt_create_iv')) {
			$randomBytes = mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
		}

		else if (function_exists('openssl_random_pseudo_bytes')) {
			$randomBytes = openssl_random_pseudo_bytes($length);
		}

		else {
			throw new \Exception('No suitable function for generating a CSRF token available.');
		}

		return rtrim(strtr(base64_encode($randomBytes), '+/', '-_'), '=');

	}

}
<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Session;

/**
 * wraps native \SessionHandler
 * 
 * @author Gregor Kofler
 * 
 * @version 0.2.0 2021-05-08
 */
class NativeSessionWrapper extends \SessionHandler
{
    /**
     * @var boolean
     */
	private	$active;

	/**
	 * {@inheritdoc }
	 */  
	public function open($path, $name): bool
    {
		$this->active = parent::open($path, $name);
		return $this->active;
	}

	/**
	 * {@inheritdoc }
	 */  
	public function close(): bool
    {
		$this->active = false;
		return parent::close();
	}
	
    /**
     * get session id
     *
     * @return string
     */
    public function getId(): string
    {
    	return session_id();
    }

    /**
     * get session name
     *
     * @return string
     */
    public function getName(): string
    {
    	return session_name();
    }
}
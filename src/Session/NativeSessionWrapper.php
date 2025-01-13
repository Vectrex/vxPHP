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
 * wraps native \SessionHandler on PHP 5.4+
 *
 * @author Gregor Kofler
 *
 * @version 0.2.2 2025-01-13
 */
class NativeSessionWrapper implements \SessionHandlerInterface
{
    /**
     * @var \SessionHandler
     */
    private \SessionHandler $handler;

    /**
     * @var boolean
     */
    private bool $active = false;

    /**
     *
     */
    public function __construct()
    {
        $this->handler = new \SessionHandler();
    }

    /**
     * {@inheritdoc }
     */
    public function open(string $path, string $name): bool
    {
        $this->active = $this->handler->open($path, $name);
        return $this->active;
    }

    /**
     * {@inheritdoc }
     */
    public function close(): bool
    {
        $this->active = false;
        return $this->handler->close();
    }

    /**
     * {@inheritdoc }
     */
    public function read($id): string|false
    {
        return $this->handler->read($id);
    }

    /**
     * {@inheritdoc}
     */
    public function write($id, $data): bool
    {
        return $this->handler->write($id, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($id): bool
    {
        return $this->handler->destroy($id);
    }

    /**
     * {@inheritdoc}
     */

    public function gc(int $max_lifetime): int|false
    {
        return $this->handler->gc($max_lifetime);
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
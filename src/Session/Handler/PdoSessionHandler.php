<?php


namespace vxPHP\Session\Handler;


use vxPHP\Application\Application;

class PdoSessionHandler extends AbstractSessionHandler
{

    /**
     * @inheritDoc
     */
    public function close()
    {
        // TODO: Implement close() method.
    }

    /**
     * @inheritDoc
     */
    public function destroy($id)
    {
        // TODO: Implement destroy() method.
    }

    /**
     * @inheritDoc
     */
    public function gc($max_lifetime)
    {
        // TODO: Implement gc() method.
    }

    /**
     * @inheritDoc
     */
    public function open($path, $name)
    {
        // TODO: Implement open() method.
    }

    /**
     * @inheritDoc
     */
    public function read($id)
    {
        // TODO: Implement read() method.
    }

    /**
     * @inheritDoc
     */
    public function write($id, $data)
    {
        // TODO: Implement write() method.
    }

    /**
     * @inheritDoc
     */
    public function validateId($id)
    {
        // TODO: Implement validateId() method.
    }

    /**
     * @inheritDoc
     */
    public function updateTimestamp($id, $data)
    {
        // TODO: Implement updateTimestamp() method.
    }

    public static function createTable(\PDO $connection): void
    {
        $type = $connection->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }
}
<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Sql;

use Laminas\Db\Adapter\Driver\ConnectionInterface;
use Laminas\Db\Extra\Adapter\ExtendedAdapterInterface;

readonly class Transaction implements TransactionInterface
{
    private ConnectionInterface $connection;

    public function __construct(ExtendedAdapterInterface $adapter)
    {
        $this->connection = $adapter->getDriver()->getConnection();
    }

    public function begin(): ConnectionInterface
    {
        return $this->connection->beginTransaction();
    }

    public function rollback(): ConnectionInterface
    {
        return $this->connection->rollback();
    }

    public function commit(): ConnectionInterface
    {
        return $this->connection->commit();
    }
}

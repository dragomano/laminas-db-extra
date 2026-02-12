<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Sql;

use Laminas\Db\Adapter\Driver\ConnectionInterface;

interface TransactionInterface
{
    public function begin(): ConnectionInterface;

    public function rollback(): ConnectionInterface;

    public function commit(): ConnectionInterface;
}

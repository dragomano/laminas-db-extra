<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Result;

use Laminas\Db\Adapter\Driver\ResultInterface;

interface ExtendedResultInterface extends ResultInterface
{
    public function getGeneratedValues(string $name = 'id'): array;
}

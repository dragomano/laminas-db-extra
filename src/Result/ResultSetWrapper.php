<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Result;

use ArrayObject;
use Laminas\Db\Adapter\Driver\ResultInterface;
use Laminas\Db\ResultSet\ResultSet;

class ResultSetWrapper implements ResultInterface
{
    private int $position = 0;

    public function __construct(private readonly ResultSet $resultSet) {}

    public function getGeneratedValue(string $name = 'id'): null
    {
        return null;
    }

    public function count(): int
    {
        return $this->resultSet->count();
    }

    public function current(): array|ArrayObject|null
    {
        return $this->resultSet->current();
    }

    public function next(): void
    {
        $this->resultSet->next();
        $this->position++;
    }

    public function key(): int
    {
        return $this->position;
    }

    public function valid(): bool
    {
        return $this->resultSet->valid();
    }

    public function rewind(): void
    {
        $this->resultSet->rewind();
        $this->position = 0;
    }

    public function getAffectedRows(): int
    {
        return $this->resultSet->count();
    }

    public function getResource(): ResultSet
    {
        return $this->resultSet;
    }

    public function buffer(): ResultSet
    {
        return $this->resultSet->buffer();
    }

    public function isBuffered(): ?bool
    {
        return $this->resultSet->isBuffered();
    }

    public function isQueryResult(): bool
    {
        return true;
    }

    public function getFieldCount(): int
    {
        return $this->resultSet->getFieldCount();
    }
}

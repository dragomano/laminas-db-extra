<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Sql\Operations;

use Laminas\Db\Sql\Delete;

class ExtendedDelete extends Delete
{
    public function __construct($table = null, private readonly string $prefix = '')
    {
        parent::__construct($table);
    }

    public function from($table): static
    {
        $table = $this->prefix . $table;

        return parent::from($table);
    }
}

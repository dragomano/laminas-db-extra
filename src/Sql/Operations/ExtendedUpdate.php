<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Sql\Operations;

use Laminas\Db\Sql\Update;

class ExtendedUpdate extends Update
{
    public function __construct($table = null, private readonly string $prefix = '')
    {
        parent::__construct($table);
    }

    public function table($table): static
    {
        $table = $this->prefix . $table;

        return parent::table($table);
    }
}

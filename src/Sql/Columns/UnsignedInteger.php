<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Sql\Columns;

use Laminas\Db\Sql\Ddl\Column\Integer;

class UnsignedInteger extends Integer
{
    public function __construct($name = null, $nullable = false, $default = 0, array $options = [])
    {
        $options = array_merge(['unsigned' => true], $options);

        parent::__construct($name, $nullable, $default, $options);
    }
}

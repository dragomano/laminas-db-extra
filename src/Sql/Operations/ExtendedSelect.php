<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Sql\Operations;

use Laminas\Db\Sql\Select;

class ExtendedSelect extends Select
{
    public function __construct($table = null, private readonly string $prefix = '')
    {
        parent::__construct($table);
    }

    public function from($table): static
    {
        if (is_string($table)) {
            $table = $this->prefix . $table;
        } elseif (is_array($table)) {
            foreach ($table as $alias => $tbl) {
                if (is_string($tbl)) {
                    $table[$alias] = $this->prefix . $tbl;
                }
            }

            $this->table = $table;

            return $this;
        }

        return parent::from($table);
    }

    public function join($name, $on, $columns = self::SQL_STAR, $type = self::JOIN_INNER): static
    {
        if (is_string($name)) {
            $name = $this->prefix . $name;
        } elseif (is_array($name)) {
            foreach ($name as $alias => $tbl) {
                if (is_string($tbl)) {
                    $name[$alias] = $this->prefix . $tbl;
                }
            }
        }

        return parent::join($name, $on, $columns, $type);
    }
}

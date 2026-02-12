<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Sql\Columns;

class SmallInteger extends UnsignedInteger
{
    protected $type = 'SMALLINT';
}

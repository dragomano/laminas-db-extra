<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Sql\Columns;

use Laminas\Db\Adapter\Platform\Postgresql;
use Laminas\Db\Extra\Adapter\DbPlatform;
use Laminas\Db\Sql\Ddl\Column\AbstractLengthColumn;

class MediumText extends AbstractLengthColumn
{
    protected $type = 'MEDIUMTEXT';

    public function __construct($name, $length = null, $nullable = false, $default = null, array $options = [])
    {
        $platform = DbPlatform::get();

        if ($platform instanceof Postgresql) {
            $this->type = 'TEXT';
        }

        parent::__construct($name, $length, $nullable, $default, $options);
    }
}

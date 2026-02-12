<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Sql\Columns;

use Laminas\Db\Adapter\Platform\Postgresql;
use Laminas\Db\Extra\Adapter\DbPlatform;

class TinyInteger extends UnsignedInteger
{
    protected $type = 'TINYINT';

    public function __construct($name = null, $nullable = false, $default = 0, array $options = [])
    {
        $platform = DbPlatform::get();

        if ($platform instanceof Postgresql) {
            $this->type = 'SMALLINT';
        }

        parent::__construct($name, $nullable, $default, $options);
    }
}

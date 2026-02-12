<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Sql\Columns;

use Laminas\Db\Adapter\Platform\Postgresql;
use Laminas\Db\Extra\Adapter\DbPlatform;
use ReflectionClass;
use ReflectionException;

class AutoIncrementInteger extends UnsignedInteger
{
    /**
     * @throws ReflectionException
     */
    public function __construct($name = 'id', $nullable = false, $default = null, array $options = [])
    {
        $platform = DbPlatform::get();

        if ($platform instanceof Postgresql) {
            parent::__construct($name, $nullable, $default, $options);

            $this->setTypeToSerial();
        } else {
            $options = array_merge(['autoincrement' => true], $options);

            parent::__construct($name, $nullable, $default, $options);
        }
    }

    /**
     * @throws ReflectionException
     */
    protected function setTypeToSerial(): void
    {
        $reflection = new ReflectionClass($this);
        $typeProp = $reflection->getParentClass()->getProperty('type');
        $typeProp->setValue($this, 'SERIAL');
    }
}

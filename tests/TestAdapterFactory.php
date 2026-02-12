<?php

declare(strict_types=1);

namespace Tests;

use Laminas\Db\Extra\Adapter\ExtendedAdapter;
use Laminas\Db\Extra\Adapter\ExtendedAdapterInterface;

class TestAdapterFactory
{
    public static function create(): ExtendedAdapterInterface
    {
        return new ExtendedAdapter([
            'driver'   => 'Pdo_Sqlite',
            'database' => ':memory:',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Adapter;

use Laminas\Db\Adapter\Platform\Mysql;
use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Adapter\Platform\Postgresql;
use Laminas\Db\Adapter\Platform\Sqlite;

class AdapterFactory
{
    public static function create(array $config = []): ExtendedAdapterInterface
    {
        $driver   = $config['driver'] ?? 'Pdo_Mysql';
        $profiler = new ExtendedProfiler(self::getPlatform($driver));

        $baseConfig = [
            'driver'   => $driver,
            'hostname' => $config['hostname'] ?? 'localhost',
            'database' => $config['database'] ?? '',
            'prefix'   => $config['prefix'] ?? '',
            'username' => $config['username'] ?? '',
            'password' => $config['password'] ?? '',
            'profiler' => $profiler,
        ];

        return new ExtendedAdapter(array_merge($baseConfig, $config));
    }

    protected static function getPlatform(string $driver): PlatformInterface
    {
        return match ($driver) {
            'Pdo_Pgsql'  => new Postgresql(),
            'Pdo_Sqlite' => new Sqlite(),
            default      => new Mysql(),
        };
    }
}

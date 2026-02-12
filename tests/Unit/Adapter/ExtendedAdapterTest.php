<?php

declare(strict_types=1);

use Laminas\Db\Adapter\Platform\Mysql;
use Laminas\Db\Adapter\Platform\Postgresql;
use Laminas\Db\Adapter\Platform\Sqlite;
use Laminas\Db\Extra\Adapter\ExtendedAdapter;
use Laminas\Db\Extra\Sql\ExtendedSql;
use Tests\ReflectionAccessor;

describe('ExtendedAdapter', function () {
    it('returns the correct prefix from connection parameters', function () {
        $adapter = new ExtendedAdapter([
            'driver'   => 'Pdo_Mysql',
            'database' => 'test_db',
            'username' => 'test_user',
            'password' => 'test_pass',
            'hostname' => 'localhost',
            'prefix'   => 'smf_',
        ]);

        expect($adapter->getPrefix())->toBe('smf_');
    });

    it('returns empty string when prefix is not set', function () {
        $adapter = new ExtendedAdapter([
            'driver'   => 'Pdo_Mysql',
            'database' => 'test_db',
            'username' => 'test_user',
            'password' => 'test_pass',
            'hostname' => 'localhost',
        ]);

        expect($adapter->getPrefix())->toBe('');
    });

    it('returns ExtendedSql instance from getSql method', function () {
        $adapter = new ExtendedAdapter([
            'driver'   => 'Pdo_Mysql',
            'database' => 'test_db',
            'username' => 'test_user',
            'password' => 'test_pass',
            'hostname' => 'localhost',
            'prefix'   => 'smf_',
        ]);

        $sql = new ExtendedSql($adapter);

        expect($sql)->toBeInstanceOf(ExtendedSql::class);
    });

    it('returns the full config array', function () {
        $config = [
            'driver'   => 'Pdo_Mysql',
            'database' => 'test_db',
            'username' => 'test_user',
            'password' => 'test_pass',
            'hostname' => 'localhost',
            'prefix'   => 'test_',
        ];

        $adapter = new ExtendedAdapter($config);

        expect($adapter->getConfig())->toBe($config);
    });

    it('returns MySQL as title for MySQL platform', function () {
        $adapter = new ExtendedAdapter([
            'driver' => 'Pdo_Mysql',
        ]);

        $platform = Mockery::mock(Mysql::class);
        $platform->shouldReceive('getName')->andReturn('MySQL');

        $reflection = new ReflectionAccessor($adapter);
        $reflection->setProperty('platform', $platform);

        expect($adapter->getTitle())->toBe('MySQL');
    });

    it('returns PostgreSQL as title for PostgreSQL platform', function () {
        $adapter = new ExtendedAdapter([
            'driver' => 'Pdo_Pgsql',
        ]);

        $platform = Mockery::mock(Postgresql::class);
        $platform->shouldReceive('getName')->andReturn('PostgreSQL');

        $reflection = new ReflectionAccessor($adapter);
        $reflection->setProperty('platform', $platform);

        expect($adapter->getTitle())->toBe('PostgreSQL');
    });

    it('returns SQLite as title for SQLite platform', function () {
        $adapter = new ExtendedAdapter([
            'driver' => 'Pdo_Sqlite',
        ]);

        $platform = Mockery::mock(Sqlite::class);
        $platform->shouldReceive('getName')->andReturn('SQLite');

        $reflection = new ReflectionAccessor($adapter);
        $reflection->setProperty('platform', $platform);

        expect($adapter->getTitle())->toBe('SQLite');
    });

    it('returns database version for SQLite', function () {
        $adapter = new ExtendedAdapter([
            'driver' => 'Pdo_Sqlite',
            'database' => ':memory:',
        ]);

        expect($adapter->getVersion())->toMatch('/\\d+\\.\\d+\\.\\d+/');
    });

    afterEach(function () {
        Mockery::close();
    });
});

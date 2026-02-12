<?php

declare(strict_types=1);

use Laminas\Db\Extra\Adapter\AdapterFactory;
use Laminas\Db\Extra\Adapter\ExtendedAdapter;
use Laminas\Db\Extra\Adapter\ExtendedAdapterInterface;

describe('AdapterFactory', function () {
    it('creates adapter with default config', function () {
        $adapter = AdapterFactory::create();

        expect($adapter)->toBeInstanceOf(ExtendedAdapterInterface::class)
            ->and($adapter)->toBeInstanceOf(ExtendedAdapter::class);
    });

    it('creates adapter with custom config', function () {
        $adapter = AdapterFactory::create([
            'driver'   => 'Pdo_Pgsql',
            'hostname' => 'localhost',
            'database' => 'testdb',
            'prefix'   => 'test_',
            'username' => 'user',
            'password' => 'pass',
        ]);

        expect($adapter)->toBeInstanceOf(ExtendedAdapter::class);
    });

    it('creates adapter with Pdo_Pgsql driver', function () {
        $adapter = AdapterFactory::create([
            'driver' => 'Pdo_Pgsql',
        ]);

        expect($adapter)->toBeInstanceOf(ExtendedAdapter::class);
    });

    it('creates adapter with Pdo_Sqlite driver', function () {
        $adapter = AdapterFactory::create([
            'driver'   => 'Pdo_Sqlite',
            'database' => ':memory:',
        ]);

        expect($adapter)->toBeInstanceOf(ExtendedAdapter::class);
    });

    it('creates adapter with custom prefix', function () {
        $adapter = AdapterFactory::create([
            'prefix' => 'custom_',
        ]);

        expect($adapter)->toBeInstanceOf(ExtendedAdapter::class);
    });
});

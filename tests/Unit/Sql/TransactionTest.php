<?php

declare(strict_types=1);

use Laminas\Db\Adapter\Driver\ConnectionInterface;
use Laminas\Db\Adapter\Driver\DriverInterface;
use Laminas\Db\Extra\Adapter\ExtendedAdapterInterface;
use Laminas\Db\Extra\Sql\Transaction;
use Tests\ReflectionAccessor;

describe('Transaction', function () {
    it('sets connection from adapter in constructor', function () {
        $connection = mock(ConnectionInterface::class);
        $driver = mock(DriverInterface::class);
        $driver->shouldReceive('getConnection')->andReturn($connection);
        $adapter = mock(ExtendedAdapterInterface::class);
        $adapter->shouldReceive('getDriver')->andReturn($driver);

        $transaction = new ReflectionAccessor(new Transaction($adapter));
        $actualConnection = $transaction->getProperty('connection');

        expect($actualConnection)->toBe($connection);
    });

    it('begins transaction and returns connection', function () {
        $connection = mock(ConnectionInterface::class);
        $connection->shouldReceive('beginTransaction')->andReturn($connection);
        $driver = mock(DriverInterface::class);
        $driver->shouldReceive('getConnection')->andReturn($connection);
        $adapter = mock(ExtendedAdapterInterface::class);
        $adapter->shouldReceive('getDriver')->andReturn($driver);

        $transaction = new Transaction($adapter);

        $result = $transaction->begin();

        expect($result)->toBe($connection);
    });

    it('rolls back transaction and returns connection', function () {
        $connection = mock(ConnectionInterface::class);
        $connection->shouldReceive('rollback')->andReturn($connection);
        $driver = mock(DriverInterface::class);
        $driver->shouldReceive('getConnection')->andReturn($connection);
        $adapter = mock(ExtendedAdapterInterface::class);
        $adapter->shouldReceive('getDriver')->andReturn($driver);

        $transaction = new Transaction($adapter);

        $result = $transaction->rollback();

        expect($result)->toBe($connection);
    });

    it('commits transaction and returns connection', function () {
        $connection = mock(ConnectionInterface::class);
        $connection->shouldReceive('commit')->andReturn($connection);
        $driver = mock(DriverInterface::class);
        $driver->shouldReceive('getConnection')->andReturn($connection);
        $adapter = mock(ExtendedAdapterInterface::class);
        $adapter->shouldReceive('getDriver')->andReturn($driver);

        $transaction = new Transaction($adapter);

        $result = $transaction->commit();

        expect($result)->toBe($connection);
    });
});

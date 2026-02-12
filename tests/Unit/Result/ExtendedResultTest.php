<?php

declare(strict_types=1);

use Laminas\Db\Adapter\Driver\ResultInterface;
use Laminas\Db\Extra\Adapter\ExtendedAdapterInterface;
use Laminas\Db\Extra\Result\ExtendedResult;
use Laminas\Db\Extra\Result\ResultSetWrapper;
use Laminas\Db\ResultSet\ResultSet;

describe('ExtendedResult', function () {
    it('constructs with result and adapter', function () {
        $result = mock(ResultInterface::class);
        $adapter = mock(ExtendedAdapterInterface::class);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult)->toBeInstanceOf(ExtendedResult::class);
    });

    it('delegates count to result', function () {
        $result = mock(ResultInterface::class);
        $result->shouldReceive('count')->andReturn(5);
        $adapter = mock(ExtendedAdapterInterface::class);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->count())->toBe(5);
    });

    it('delegates current to result', function () {
        $row = ['id' => 1, 'name' => 'test'];
        $result = mock(ResultInterface::class);
        $result->shouldReceive('current')->andReturn($row);
        $adapter = mock(ExtendedAdapterInterface::class);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->current())->toBe($row);
    });

    it('delegates next to result', function () {
        $result = mock(ResultInterface::class);
        $result->shouldReceive('next')->once();
        $adapter = mock(ExtendedAdapterInterface::class);

        $extendedResult = new ExtendedResult($result, $adapter);
        $extendedResult->next();
    });

    it('delegates key to result', function () {
        $result = mock(ResultInterface::class);
        $result->shouldReceive('key')->andReturn(0);
        $adapter = mock(ExtendedAdapterInterface::class);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->key())->toBe(0);
    });

    it('delegates valid to result', function () {
        $result = mock(ResultInterface::class);
        $result->shouldReceive('valid')->andReturn(true);
        $adapter = mock(ExtendedAdapterInterface::class);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->valid())->toBeTrue();
    });

    it('delegates rewind to result', function () {
        $result = mock(ResultInterface::class);
        $result->shouldReceive('rewind')->once();
        $adapter = mock(ExtendedAdapterInterface::class);

        $extendedResult = new ExtendedResult($result, $adapter);
        $extendedResult->rewind();
    });

    it('delegates getAffectedRows to result', function () {
        $result = mock(ResultInterface::class);
        $result->shouldReceive('getAffectedRows')->andReturn(10);
        $adapter = mock(ExtendedAdapterInterface::class);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->getAffectedRows())->toBe(10);
    });

    it('delegates getResource to result', function () {
        $resource = new stdClass();
        $result = mock(ResultInterface::class);
        $result->shouldReceive('getResource')->andReturn($resource);
        $adapter = mock(ExtendedAdapterInterface::class);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->getResource())->toBe($resource);
    });

    it('delegates buffer to result', function () {
        $result = mock(ResultInterface::class);
        $result->shouldReceive('buffer')->once();
        $adapter = mock(ExtendedAdapterInterface::class);

        $extendedResult = new ExtendedResult($result, $adapter);
        $extendedResult->buffer();
    });

    it('delegates isBuffered to result', function () {
        $result = mock(ResultInterface::class);
        $result->shouldReceive('isBuffered')->andReturn(true);
        $adapter = mock(ExtendedAdapterInterface::class);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->isBuffered())->toBeTrue();
    });

    it('delegates isQueryResult to result', function () {
        $result = mock(ResultInterface::class);
        $result->shouldReceive('isQueryResult')->andReturn(true);
        $adapter = mock(ExtendedAdapterInterface::class);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->isQueryResult())->toBeTrue();
    });

    it('delegates getFieldCount to result', function () {
        $result = mock(ResultInterface::class);
        $result->shouldReceive('getFieldCount')->andReturn(3);
        $adapter = mock(ExtendedAdapterInterface::class);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->getFieldCount())->toBe(3);
    });

    it('returns generated value from result for non-postgresql', function () {
        $result = mock(ResultInterface::class);
        $result->shouldReceive('getGeneratedValue')->with('id')->andReturn(42);

        $adapter = mock(ExtendedAdapterInterface::class);
        $driver = mock(Laminas\Db\Adapter\Driver\DriverInterface::class);
        $driver->shouldReceive('getDatabasePlatformName')->andReturn('Mysql');
        $adapter->shouldReceive('getDriver')->andReturn($driver);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->getGeneratedValue())->toBe(42);
    });

    it('returns generated value from result row for postgresql', function () {
        $result = mock(ResultInterface::class);
        $result->shouldReceive('isQueryResult')->andReturn(true);
        $result->shouldReceive('rewind')->once();
        $result->shouldReceive('current')->andReturn(['id' => 123, 'name' => 'test']);

        $driver = mock(Laminas\Db\Adapter\Driver\DriverInterface::class);
        $driver->shouldReceive('getDatabasePlatformName')->andReturn('Postgresql');

        $adapter = mock(ExtendedAdapterInterface::class);
        $adapter->shouldReceive('getDriver')->andReturn($driver);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->getGeneratedValue())->toBe(123);
    });

    it('returns null when row does not have column for postgresql', function () {
        $result = mock(ResultInterface::class);
        $result->shouldReceive('isQueryResult')->andReturn(true);
        $result->shouldReceive('rewind')->once();
        $result->shouldReceive('current')->andReturn(['other_id' => 123]);
        $result->shouldReceive('getGeneratedValue')->with('id')->andReturn(null);

        $driver = mock(Laminas\Db\Adapter\Driver\DriverInterface::class);
        $driver->shouldReceive('getDatabasePlatformName')->andReturn('Postgresql');

        $adapter = mock(ExtendedAdapterInterface::class);
        $adapter->shouldReceive('getDriver')->andReturn($driver);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->getGeneratedValue())->toBeNull();
    });

    it('returns null when current row is null for postgresql', function () {
        $result = mock(ResultInterface::class);
        $result->shouldReceive('isQueryResult')->andReturn(true);
        $result->shouldReceive('rewind')->once();
        $result->shouldReceive('current')->andReturn(null);
        $result->shouldReceive('getGeneratedValue')->with('id')->andReturn(null);

        $driver = mock(Laminas\Db\Adapter\Driver\DriverInterface::class);
        $driver->shouldReceive('getDatabasePlatformName')->andReturn('Postgresql');

        $adapter = mock(ExtendedAdapterInterface::class);
        $adapter->shouldReceive('getDriver')->andReturn($driver);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->getGeneratedValue())->toBeNull();
    });

    it('falls back to result getGeneratedValue when not query result for postgresql', function () {
        $result = mock(ResultInterface::class);
        $result->shouldReceive('isQueryResult')->andReturn(false);
        $result->shouldReceive('getGeneratedValue')->with('id')->andReturn(99);

        $driver = mock(Laminas\Db\Adapter\Driver\DriverInterface::class);
        $driver->shouldReceive('getDatabasePlatformName')->andReturn('Postgresql');

        $adapter = mock(ExtendedAdapterInterface::class);
        $adapter->shouldReceive('getDriver')->andReturn($driver);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->getGeneratedValue())->toBe(99);
    });

    it('returns generated values from query result', function () {
        $rows = [
            ['id' => 1, 'name' => 'a'],
            ['id' => 2, 'name' => 'b'],
            ['id' => 3, 'name' => 'c'],
        ];

        $result = mock(ResultInterface::class);
        $result->shouldReceive('isQueryResult')->andReturn(true);
        $result->shouldReceive('rewind')->once();

        $callCount = 0;
        $result->shouldReceive('valid')->andReturnUsing(function () use (&$callCount, $rows) {
            return $callCount < count($rows);
        });

        $result->shouldReceive('current')->andReturnUsing(function () use (&$callCount, $rows) {
            return $rows[$callCount] ?? null;
        });

        $result->shouldReceive('next')->andReturnUsing(function () use (&$callCount) {
            $callCount++;
        });

        $adapter = mock(ExtendedAdapterInterface::class);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->getGeneratedValues())->toBe([1, 2, 3]);
    });

    it('returns empty array when no generated values', function () {
        $result = mock(ResultInterface::class);
        $result->shouldReceive('isQueryResult')->andReturn(false);
        $result->shouldReceive('getGeneratedValue')->with('id')->andReturn(null);
        $result->shouldReceive('getResource')->andReturn(null);
        $result->shouldReceive('count')->andReturn(0);

        $adapter = mock(ExtendedAdapterInterface::class);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->getGeneratedValues())->toBe([]);
    });

    it('returns single value in array when not query result', function () {
        $result = mock(ResultInterface::class);
        $result->shouldReceive('isQueryResult')->andReturn(false);
        $result->shouldReceive('getGeneratedValue')->with('id')->andReturn(42);
        $result->shouldReceive('getResource')->andReturn(null);
        $result->shouldReceive('count')->andReturn(1);

        $adapter = mock(ExtendedAdapterInterface::class);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->getGeneratedValues())->toBe([42]);
    });

    it('extracts table name from PDO statement', function () {
        $pdoStatement = mock(PDOStatement::class);
        $pdoStatement->queryString /** @lang text */
            = "INSERT INTO `users` (id, name) VALUES (1, 'test')";

        $result = mock(ResultInterface::class);
        $result->shouldReceive('isQueryResult')->andReturn(false);
        $result->shouldReceive('getGeneratedValue')->with('id')->andReturn(1);
        $result->shouldReceive('getResource')->andReturn($pdoStatement);
        $result->shouldReceive('count')->andReturn(1);

        $adapter = mock(ExtendedAdapterInterface::class);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->getGeneratedValues())->toBe([1]);
    });

    it('extracts table name without backticks', function () {
        $pdoStatement = mock(PDOStatement::class);
        $pdoStatement->queryString /** @lang text */
            = "INSERT INTO users (id, name) VALUES (1, 'test')";

        $result = mock(ResultInterface::class);
        $result->shouldReceive('isQueryResult')->andReturn(false);
        $result->shouldReceive('getGeneratedValue')->with('id')->andReturn(1);
        $result->shouldReceive('getResource')->andReturn($pdoStatement);
        $result->shouldReceive('count')->andReturn(1);

        $adapter = mock(ExtendedAdapterInterface::class);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->getGeneratedValues())->toBe([1]);
    });

    it('extracts table name from REPLACE INTO', function () {
        $pdoStatement = mock(PDOStatement::class);
        $pdoStatement->queryString /** @lang text */
            = "REPLACE INTO products (id, name) VALUES (1, 'product')";

        $result = mock(ResultInterface::class);
        $result->shouldReceive('isQueryResult')->andReturn(false);
        $result->shouldReceive('getGeneratedValue')->with('id')->andReturn(1);
        $result->shouldReceive('getResource')->andReturn($pdoStatement);
        $result->shouldReceive('count')->andReturn(1);

        $adapter = mock(ExtendedAdapterInterface::class);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->getGeneratedValues())->toBe([1]);
    });

    it('queries for range when count > 1', function () {
        $pdoStatement = mock(PDOStatement::class);
        $pdoStatement->queryString /** @lang text */
            = "INSERT INTO `items` (id, name) VALUES (1, 'test')";

        $result = mock(ResultInterface::class);
        $result->shouldReceive('isQueryResult')->andReturn(false);
        $result->shouldReceive('getGeneratedValue')->with('id')->andReturn(10);
        $result->shouldReceive('getResource')->andReturn($pdoStatement);
        $result->shouldReceive('count')->andReturn(3);

        $queryResult = mock(ResultInterface::class);
        $queryResult->shouldReceive('toArray')->andReturn([
            ['id' => 10],
            ['id' => 11],
            ['id' => 12],
        ]);

        $adapter = mock(ExtendedAdapterInterface::class);
        $adapter->shouldReceive('query')
            ->with(/** @lang text */ 'SELECT id FROM items WHERE id BETWEEN ? AND ?', [10, 12])
            ->andReturn($queryResult);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->getGeneratedValues())->toBe([10, 11, 12]);
    });

    it('handles exception during range query', function () {
        $pdoStatement = mock(PDOStatement::class);
        $pdoStatement->queryString /** @lang text */
            = "INSERT INTO `items` (id, name) VALUES (1, 'test')";

        $result = mock(ResultInterface::class);
        $result->shouldReceive('isQueryResult')->andReturn(false);
        $result->shouldReceive('getGeneratedValue')->with('id')->andReturn(10);
        $result->shouldReceive('getResource')->andReturn($pdoStatement);
        $result->shouldReceive('count')->andReturn(3);

        $adapter = mock(ExtendedAdapterInterface::class);
        $adapter->shouldReceive('query')
            ->andThrow(new Exception('Query failed'));

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->getGeneratedValues())->toBe([10]);
    });

    it('returns null when resource is not PDOStatement', function () {
        $result = mock(ResultInterface::class);
        $result->shouldReceive('isQueryResult')->andReturn(false);
        $result->shouldReceive('getGeneratedValue')->with('id')->andReturn(1);
        $result->shouldReceive('getResource')->andReturn(new stdClass());
        $result->shouldReceive('count')->andReturn(1);

        $adapter = mock(ExtendedAdapterInterface::class);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->getGeneratedValues())->toBe([1]);
    });

    it('returns null when queryString does not match pattern', function () {
        $pdoStatement = mock(PDOStatement::class);
        $pdoStatement->queryString /** @lang text */
            = 'SELECT * FROM users';

        $result = mock(ResultInterface::class);
        $result->shouldReceive('isQueryResult')->andReturn(false);
        $result->shouldReceive('getGeneratedValue')->with('id')->andReturn(1);
        $result->shouldReceive('getResource')->andReturn($pdoStatement);
        $result->shouldReceive('count')->andReturn(1);

        $adapter = mock(ExtendedAdapterInterface::class);

        $extendedResult = new ExtendedResult($result, $adapter);

        expect($extendedResult->getGeneratedValues())->toBe([1]);
    });
});

describe('ResultSetWrapper', function () {
    it('constructs with result set', function () {
        $resultSet = new ResultSet();
        $wrapper = new ResultSetWrapper($resultSet);

        expect($wrapper)->toBeInstanceOf(ResultSetWrapper::class);
    });

    it('returns null for getGeneratedValue', function () {
        $resultSet = new ResultSet();
        $wrapper = new ResultSetWrapper($resultSet);

        expect($wrapper->getGeneratedValue())->toBeNull();
    });

    it('returns count from result set', function () {
        $resultSet = new ResultSet();
        $resultSet->initialize(new ArrayIterator([['id' => 1], ['id' => 2]]));

        $wrapper = new ResultSetWrapper($resultSet);

        expect($wrapper->count())->toBe(2);
    });

    it('returns current from result set', function () {
        $resultSet = new ResultSet();
        $resultSet->initialize(new ArrayIterator([['id' => 1, 'name' => 'test']]));

        $wrapper = new ResultSetWrapper($resultSet);

        expect($wrapper->current()['id'])->toBe(1)
            ->and($wrapper->current()['name'])->toBe('test');
    });

    it('advances position on next', function () {
        $resultSet = new ResultSet();
        $resultSet->initialize(new ArrayIterator([['id' => 1], ['id' => 2]]));

        $wrapper = new ResultSetWrapper($resultSet);

        expect($wrapper->key())->toBe(0);

        $wrapper->next();

        expect($wrapper->key())->toBe(1);
    });

    it('returns valid from result set', function () {
        $resultSet = new ResultSet();
        $resultSet->initialize(new ArrayIterator([['id' => 1]]));

        $wrapper = new ResultSetWrapper($resultSet);

        expect($wrapper->valid())->toBeTrue();

        $wrapper->next();

        expect($wrapper->valid())->toBeFalse();
    });

    it('resets position on rewind', function () {
        $resultSet = new ResultSet();
        $resultSet->initialize(new ArrayIterator([['id' => 1], ['id' => 2]]));

        $wrapper = new ResultSetWrapper($resultSet);

        $wrapper->next();
        expect($wrapper->key())->toBe(1);

        $wrapper->rewind();
        expect($wrapper->key())->toBe(0);
    });

    it('returns count for getAffectedRows', function () {
        $resultSet = new ResultSet();
        $resultSet->initialize(new ArrayIterator([['id' => 1], ['id' => 2], ['id' => 3]]));

        $wrapper = new ResultSetWrapper($resultSet);

        expect($wrapper->getAffectedRows())->toBe(3);
    });

    it('returns result set from getResource', function () {
        $resultSet = new ResultSet();
        $wrapper = new ResultSetWrapper($resultSet);

        expect($wrapper->getResource())->toBe($resultSet);
    });

    it('delegates buffer to result set', function () {
        $resultSet = mock(ResultSet::class);
        $resultSet->shouldReceive('buffer')->andReturn($resultSet);

        $wrapper = new ResultSetWrapper($resultSet);
        $result = $wrapper->buffer();

        expect($result)->toBe($resultSet);
    });

    it('delegates isBuffered to result set', function () {
        $resultSet = mock(ResultSet::class);
        $resultSet->shouldReceive('isBuffered')->andReturn(true);

        $wrapper = new ResultSetWrapper($resultSet);

        expect($wrapper->isBuffered())->toBeTrue();
    });

    it('returns true for isQueryResult', function () {
        $resultSet = new ResultSet();
        $wrapper = new ResultSetWrapper($resultSet);

        expect($wrapper->isQueryResult())->toBeTrue();
    });

    it('delegates getFieldCount to result set', function () {
        $resultSet = mock(ResultSet::class);
        $resultSet->shouldReceive('getFieldCount')->andReturn(5);

        $wrapper = new ResultSetWrapper($resultSet);

        expect($wrapper->getFieldCount())->toBe(5);
    });

    it('iterates over all rows', function () {
        $resultSet = new ResultSet();
        $resultSet->initialize(new ArrayIterator([
            ['id' => 1, 'name' => 'a'],
            ['id' => 2, 'name' => 'b'],
            ['id' => 3, 'name' => 'c'],
        ]));

        $wrapper = new ResultSetWrapper($resultSet);
        $rows = [];

        foreach ($wrapper as $key => $row) {
            $rows[$key] = $row;
        }

        expect($rows)->toHaveCount(3)
            ->and($rows[0]['id'])->toBe(1)
            ->and($rows[1]['id'])->toBe(2)
            ->and($rows[2]['id'])->toBe(3);
    });

    it('handles empty result set', function () {
        $resultSet = new ResultSet();
        $resultSet->initialize(new ArrayIterator([]));

        $wrapper = new ResultSetWrapper($resultSet);

        expect($wrapper->count())->toBe(0)
            ->and($wrapper->valid())->toBeFalse();
    });

    it('handles ArrayObject rows', function () {
        $resultSet = new ResultSet();
        $resultSet->initialize(new ArrayIterator([['id' => 1, 'name' => 'test']]));

        $wrapper = new ResultSetWrapper($resultSet);

        $current = $wrapper->current();
        expect($current['id'])->toBe(1);
    });
});

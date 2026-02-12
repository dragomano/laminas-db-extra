<?php

declare(strict_types=1);

use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\Adapter\Driver\ResultInterface;
use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Extra\Result\ResultSetWrapper;
use Laminas\Db\Extra\Sql\Operations\ExtendedInsert;
use Laminas\Db\ResultSet\ResultSet;
use Tests\ReflectionAccessor;

describe('ExtendedInsert', function () {
    beforeEach(function () {
        $this->platform = mock(PlatformInterface::class);
        $this->adapter = mock(AdapterInterface::class);
        $this->adapter->shouldReceive('getPlatform')->andReturn($this->platform);
        $this->result = mock(ResultInterface::class);

        $this->insert = new ExtendedInsert();
    });

    describe('base functionality', function () {
        it('constructs with prefix', function () {
            $insert = new ExtendedInsert('test', 'prefix_');

            expect($insert)->toBeInstanceOf(ExtendedInsert::class);
        });

        it('adds prefix to string table in into', function () {
            $insert = new ExtendedInsert('test', 'prefix_');

            $result = $insert->into('users');

            expect($result)->toBeInstanceOf(ExtendedInsert::class);

            $reflection = new ReflectionAccessor($insert);
            $tableProperty = $reflection->getProperty('table');

            expect($tableProperty)->toBe('prefix_users');
        });

        it('does not add prefix when prefix is empty', function () {
            $insert = new ExtendedInsert('test', '');

            $insert->into('users');

            $reflection = new ReflectionAccessor($insert);
            $tableProperty = $reflection->getProperty('table');

            expect($tableProperty)->toBe('users');
        });
    });

    describe('executeBatchInsert', function () {
        it('returns empty result for empty batch array', function () {
            $insert = new ExtendedInsert();
            $insert->into('test_table');

            $this->adapter->shouldReceive('query')
                ->once()
                ->with('SELECT 1 WHERE 0 = 1', [])
                ->andReturn($this->result);

            $result = $insert->batch([])->executeBatch($this->adapter);

            expect($result)->toBe($this->result);
        });

        it('executes batch INSERT for MySQL', function () {
            $batchData = [
                ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
                ['id' => 2, 'name' => 'Jane Doe', 'email' => 'jane@example.com'],
            ];
            $this->insert->into('test_table');

            $this->platform->shouldReceive('getName')->andReturn('MySQL');

            $this->adapter->shouldReceive('query')
                ->once()
                ->with(
                    /** @lang text */
                    'INSERT INTO test_table (id,name,email) VALUES (?,?,?),(?,?,?)',
                    [1, 'John Doe', 'john@example.com', 2, 'Jane Doe', 'jane@example.com']
                )
                ->andReturn($this->result);

            $result = $this->insert->batch($batchData)->executeBatch($this->adapter);

            expect($result)->toBe($this->result);
        });

        it('executes batch INSERT for PostgreSQL', function () {
            $batchData = [
                ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
                ['id' => 2, 'name' => 'Jane Doe', 'email' => 'jane@example.com'],
            ];
            $this->insert->into('test_table');

            $this->platform->shouldReceive('getName')->andReturn('PostgreSQL');

            $this->adapter->shouldReceive('query')
                ->once()
                ->with(
                    /** @lang text */
                    'INSERT INTO test_table (id,name,email) VALUES (?,?,?),(?,?,?)',
                    [1, 'John Doe', 'john@example.com', 2, 'Jane Doe', 'jane@example.com']
                )
                ->andReturn($this->result);

            $result = $this->insert->batch($batchData)->executeBatch($this->adapter);

            expect($result)->toBe($this->result);
        });

        it('returns ResultSetWrapper for PostgreSQL batch when returning is set', function () {
            $batchData = [
                ['id' => 1, 'name' => 'John Doe'],
                ['id' => 2, 'name' => 'Jane Doe'],
            ];
            $this->insert->into('test_table');
            $this->insert->setReturning(['id', 'name']);

            $this->platform->shouldReceive('getName')->andReturn('PostgreSQL');

            $resultSet = mock(ResultSet::class);

            $this->adapter->shouldReceive('query')
                ->once()
                ->andReturnUsing(function ($sql, $params) use ($resultSet) {
                    expect($sql)->toContain('RETURNING');

                    return $resultSet;
                });

            $result = $this->insert->batch($batchData)->executeBatch($this->adapter);

            expect($result)->toBeInstanceOf(ResultSetWrapper::class);
        });
    });

    describe('executeInsert', function () {
        it('executes INSERT with placeholders', function () {
            $this->insert->into('test_table');
            $this->insert->columns(['id', 'name', 'email']);
            $this->insert->values([1, 'John Doe', 'john@example.com']);

            $this->platform->shouldReceive('getName')->andReturn('MySQL');

            $statement = mock(AdapterInterface::class);
            $this->adapter->shouldReceive('createStatement')
                ->once()
                ->andReturn($statement);
            $statement->shouldReceive('execute')->andReturn($this->result);

            $result = $this->insert->executeInsert($this->adapter);

            expect($result)->toBe($this->result);
        });
    });

    describe('returning in constructor', function () {
        it('sets returning from constructor', function () {
            $insert = new ExtendedInsert('test', 'prefix_', ['id', 'name']);

            $reflection = new ReflectionAccessor($insert);
            $returningProperty = $reflection->getProperty('returning');

            expect($returningProperty)->toBe(['id', 'name']);
        });

        it('sets returning from constructor as string', function () {
            $insert = new ExtendedInsert('test', 'prefix_', 'id');

            $reflection = new ReflectionAccessor($insert);
            $returningProperty = $reflection->getProperty('returning');

            expect($returningProperty)->toBe(['id']);
        });
    });
});

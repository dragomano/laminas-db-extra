<?php

declare(strict_types=1);

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\Driver\ConnectionInterface;
use Laminas\Db\Adapter\Driver\DriverInterface;
use Laminas\Db\Adapter\Driver\ResultInterface;
use Laminas\Db\Adapter\Driver\StatementInterface;
use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Adapter\StatementContainerInterface;
use Laminas\Db\Extra\Adapter\ExtendedAdapterInterface;
use Laminas\Db\Extra\Result\ExtendedResult;
use Laminas\Db\Extra\Sql\ExtendedSql;
use Laminas\Db\Extra\Sql\Operations\ExtendedDelete;
use Laminas\Db\Extra\Sql\Operations\ExtendedInsert;
use Laminas\Db\Extra\Sql\Operations\ExtendedReplace;
use Laminas\Db\Extra\Sql\Operations\ExtendedSelect;
use Laminas\Db\Extra\Sql\Operations\ExtendedUpdate;
use Laminas\Db\Extra\Sql\TransactionInterface;
use Laminas\Db\Sql\PreparableSqlInterface;
use Tests\ReflectionAccessor;
use Tests\TestAdapterFactory;

describe('ExtendedSql', function () {
    beforeEach(function () {
        $platform = mock(PlatformInterface::class);
        $platform->shouldReceive('getName')->andReturn('SQLite');

        $this->adapter = mock(ExtendedAdapterInterface::class);
        $this->adapter->shouldReceive('getPrefix')->andReturn('smf_');
        $this->adapter->shouldReceive('getPlatform')->andReturn($platform);
        $this->adapter->shouldReceive('getTitle')->andReturn('SQLite');

        $driver = mock(DriverInterface::class);
        $driver->shouldReceive('getDatabasePlatformName')->andReturn('SQLite');
        $this->adapter->shouldReceive('getDriver')->andReturn($driver);

        $this->sql = new ExtendedSql($this->adapter);
    });

    it('returns ExtendedDelete from delete method without table', function () {
        $delete = $this->sql->delete();

        expect($delete)->toBeInstanceOf(ExtendedDelete::class);
    });

    it('returns ExtendedDelete from delete method with table', function () {
        $delete = $this->sql->delete('lp_tags');

        expect($delete)->toBeInstanceOf(ExtendedDelete::class)
            ->and($delete->getRawState()['table'])->toBe('smf_lp_tags');
    });

    it('returns ExtendedInsert from insert method without table', function () {
        $insert = $this->sql->insert();

        expect($insert)->toBeInstanceOf(ExtendedInsert::class);
    });

    it('returns ExtendedInsert from insert method with table', function () {
        $insert = $this->sql->insert('lp_blocks');

        expect($insert)->toBeInstanceOf(ExtendedInsert::class)
            ->and($insert->getRawState()['table'])->toBe('smf_lp_blocks');
    });

    it('returns ExtendedReplace from replace method without table', function () {
        $replace = $this->sql->replace();

        expect($replace)->toBeInstanceOf(ExtendedReplace::class);
    });

    it('returns ExtendedReplace from replace method with table', function () {
        $replace = $this->sql->replace('lp_params');

        expect($replace)->toBeInstanceOf(ExtendedReplace::class)
            ->and($replace->getRawState()['table'])->toBe('smf_lp_params');
    });

    it('returns ExtendedSelect from select method without table', function () {
        $select = $this->sql->select();

        expect($select)->toBeInstanceOf(ExtendedSelect::class);
    });

    it('returns ExtendedSelect from select method with table', function () {
        $select = $this->sql->select('lp_pages');

        expect($select)->toBeInstanceOf(ExtendedSelect::class)
            ->and($select->getRawState()['table'])->toBe('smf_lp_pages');
    });

    it('returns ExtendedUpdate from update method without table', function () {
        $update = $this->sql->update();

        expect($update)->toBeInstanceOf(ExtendedUpdate::class);
    });

    it('returns ExtendedUpdate from update method with table', function () {
        $update = $this->sql->update('lp_categories');

        expect($update)->toBeInstanceOf(ExtendedUpdate::class)
            ->and($update->getRawState()['table'])->toBe('smf_lp_categories');
    });

    it('returns prefix from getPrefix method', function () {
        expect($this->sql->getPrefix())->toBe('smf_');
    });

    it('returns adapter from getAdapter method', function () {
        expect($this->sql->getAdapter())->toBeInstanceOf(ExtendedAdapterInterface::class);
    });

    it('returns transaction from getTransaction method', function () {
        $this->sql->getAdapter()->getDriver()
            ->shouldReceive('getConnection')
            ->andReturn(mock(ConnectionInterface::class));

        $transaction = $this->sql->getTransaction();

        expect($transaction)->toBeInstanceOf(TransactionInterface::class);
    });

    it('checks if table exists', function () {
        $result = mock(ResultInterface::class);
        $result->shouldReceive('current')->andReturn(['1' => 1], null);

        $this->adapter->shouldReceive('query')->andReturn($result);

        $accessor = new ReflectionAccessor($this->sql);
        $accessor->setProperty('adapter', $this->adapter);

        expect($this->sql->tableExists('lp_blocks'))->toBeTrue()
            ->and($this->sql->tableExists('lp_nonexistent'))->toBeFalse();
    });

    it('checks if column exists', function () {
        $result = mock(ResultInterface::class);
        $result->shouldReceive('execute')->andReturn($result);

        $this->sql->getAdapter()
            ->shouldReceive('query')
            ->with('PRAGMA table_info(smf_lp_blocks)', Adapter::QUERY_MODE_EXECUTE)
            ->andReturn($result);

        // Mock the iterator behavior
        $iterator = new ArrayIterator([
            ['name' => 'block_id'],
            ['name' => 'title'],
            ['name' => 'content'],
        ]);

        $result->shouldReceive('current')->andReturnUsing(function () use ($iterator) {
            return $iterator->current();
        });
        $result->shouldReceive('valid')->andReturnUsing(function () use ($iterator) {
            return $iterator->valid();
        });
        $result->shouldReceive('next')->andReturnUsing(function () use ($iterator) {
            $iterator->next();
        });
        $result->shouldReceive('rewind')->andReturnUsing(function () use ($iterator) {
            $iterator->rewind();
        });

        expect($this->sql->columnExists('lp_blocks', 'title'))->toBeTrue()
            ->and($this->sql->columnExists('lp_blocks', 'nonexistent'))->toBeFalse();
    });

    it('executes queries', function () {
        $sqlObject = mock(PreparableSqlInterface::class);
        $result = mock(ResultInterface::class);

        $statementContainer = mock(StatementContainerInterface::class);
        $statementContainer->shouldReceive('execute')->andReturn($result);

        $statement = mock(StatementInterface::class);
        $statement->shouldReceive('prepare')->with($sqlObject)->andReturn($statementContainer);
        $statement->shouldReceive('execute')->andReturn($result);

        $this->sql->getAdapter()->getDriver()
            ->shouldReceive('createStatement')
            ->andReturn($statement);

        $sqlObject
            ->shouldReceive('prepareStatement')
            ->with($this->sql->getAdapter(), Mockery::type(StatementInterface::class))
            ->andReturn($statementContainer);


        expect($this->sql->execute($sqlObject))->toEqual(new ExtendedResult($result, $this->sql->getAdapter()));
    });

    it('execute returns null when exception is thrown', function () {
        $sqlObject = mock(PreparableSqlInterface::class);

        $statementContainer = mock(StatementContainerInterface::class);
        $statementContainer->shouldReceive('execute')->andThrow(new Exception('Database error'));

        $statement = mock(StatementInterface::class);
        $statement->shouldReceive('prepare')->with($sqlObject)->andReturn($statementContainer);

        $this->sql->getAdapter()->getDriver()
            ->shouldReceive('createStatement')
            ->andReturn($statement);

        $sqlObject
            ->shouldReceive('prepareStatement')
            ->with($this->sql->getAdapter(), Mockery::type(StatementInterface::class))
            ->andReturn($statementContainer);

        expect($this->sql->execute($sqlObject))->toBeNull();
    });

    it('executes batch insert queries', function () {
        $insert = mock(ExtendedInsert::class);
        $insert->shouldReceive('isBatch')->andReturn(true);
        $result = mock(ResultInterface::class);

        $insert->shouldReceive('executeBatch')
            ->with($this->sql->getAdapter())
            ->andReturn($result);

        expect($this->sql->execute($insert))->toEqual(new ExtendedResult($result, $this->sql->getAdapter()));
    });

    it('executes batch replace queries', function () {
        $replace = mock(ExtendedReplace::class);
        $replace->shouldReceive('isBatch')->andReturn(true);
        $result = mock(ResultInterface::class);

        $replace->shouldReceive('executeBatchReplace')
            ->with($this->sql->getAdapter())
            ->andReturn($result);

        expect($this->sql->execute($replace))->toEqual(new ExtendedResult($result, $this->sql->getAdapter()));
    });

    it('executes single replace queries', function () {
        $replace = mock(ExtendedReplace::class);
        $replace->shouldReceive('isBatch')->andReturn(false);
        $result = mock(ResultInterface::class);

        $replace->shouldReceive('executeReplace')
            ->with($this->sql->getAdapter())
            ->andReturn($result);

        expect($this->sql->execute($replace))->toEqual(new ExtendedResult($result, $this->sql->getAdapter()));
    });

    it('executes single insert queries', function () {
        $insert = mock(ExtendedInsert::class);
        $insert->shouldReceive('isBatch')->andReturn(false);
        $result = mock(ResultInterface::class);

        $insert->shouldReceive('executeInsert')
            ->with($this->sql->getAdapter())
            ->andReturn($result);

        expect($this->sql->execute($insert))->toEqual(new ExtendedResult($result, $this->sql->getAdapter()));
    });

    it('handleExecutionError does not throw exception', function () {
        $sql = new class ($this->adapter) extends ExtendedSql {
            public function testHandleError(): void
            {
                $this->handleExecutionError(new Exception('test'));
            }
        };

        expect(fn() => $sql->testHandleError())->not->toThrow(Exception::class);
    });

    it('tableExists returns true for MySQL platform', function () {
        $platform = mock(PlatformInterface::class);
        $platform->shouldReceive('getName')->andReturn('MySQL');

        $adapter = mock(ExtendedAdapterInterface::class);
        $adapter->shouldReceive('getPrefix')->andReturn('smf_');
        $adapter->shouldReceive('getPlatform')->andReturn($platform);
        $adapter->shouldReceive('getTitle')->andReturn('MySQL');

        $driver = mock(DriverInterface::class);
        $driver->shouldReceive('getDatabasePlatformName')->andReturn('MySQL');
        $adapter->shouldReceive('getDriver')->andReturn($driver);

        $metadata = mock('Laminas\Db\Metadata\MetadataInterface');
        $metadata->shouldReceive('getTableNames')->andReturn(['smf_users']);

        $sql = new ExtendedSql($adapter, $metadata);

        expect($sql->tableExists('users'))->toBeTrue();
    });

    it('tableExists returns false for MySQL platform when table not found', function () {
        $platform = mock(PlatformInterface::class);
        $platform->shouldReceive('getName')->andReturn('MySQL');

        $adapter = mock(ExtendedAdapterInterface::class);
        $adapter->shouldReceive('getPrefix')->andReturn('smf_');
        $adapter->shouldReceive('getPlatform')->andReturn($platform);
        $adapter->shouldReceive('getTitle')->andReturn('MySQL');

        $driver = mock(DriverInterface::class);
        $driver->shouldReceive('getDatabasePlatformName')->andReturn('MySQL');
        $adapter->shouldReceive('getDriver')->andReturn($driver);

        $metadata = mock('Laminas\Db\Metadata\MetadataInterface');
        $metadata->shouldReceive('getTableNames')->andReturn(['smf_users']);

        $sql = new ExtendedSql($adapter, $metadata);

        expect($sql->tableExists('posts'))->toBeFalse();
    });

    it('columnExists returns true for MySQL platform', function () {
        $platform = mock(PlatformInterface::class);
        $platform->shouldReceive('getName')->andReturn('MySQL');

        $adapter = mock(ExtendedAdapterInterface::class);
        $adapter->shouldReceive('getPrefix')->andReturn('smf_');
        $adapter->shouldReceive('getPlatform')->andReturn($platform);
        $adapter->shouldReceive('getTitle')->andReturn('MySQL');

        $driver = mock(DriverInterface::class);
        $driver->shouldReceive('getDatabasePlatformName')->andReturn('MySQL');
        $adapter->shouldReceive('getDriver')->andReturn($driver);

        $metadata = mock('Laminas\Db\Metadata\MetadataInterface');
        $metadata->shouldReceive('getColumnNames')->with('smf_users')->andReturn(['id', 'username', 'email']);

        $sql = new ExtendedSql($adapter, $metadata);

        expect($sql->columnExists('users', 'username'))->toBeTrue();
    });

    it('columnExists returns false for MySQL platform when column not found', function () {
        $platform = mock(PlatformInterface::class);
        $platform->shouldReceive('getName')->andReturn('MySQL');

        $adapter = mock(ExtendedAdapterInterface::class);
        $adapter->shouldReceive('getPrefix')->andReturn('smf_');
        $adapter->shouldReceive('getPlatform')->andReturn($platform);
        $adapter->shouldReceive('getTitle')->andReturn('MySQL');

        $driver = mock(DriverInterface::class);
        $driver->shouldReceive('getDatabasePlatformName')->andReturn('MySQL');
        $adapter->shouldReceive('getDriver')->andReturn($driver);

        $metadata = mock('Laminas\Db\Metadata\MetadataInterface');
        $metadata->shouldReceive('getColumnNames')->with('smf_users')->andReturn(['id', 'username', 'email']);

        $sql = new ExtendedSql($adapter, $metadata);

        expect($sql->columnExists('users', 'phone'))->toBeFalse();
    });

    it('tableExists returns false when getMetadata throws exception', function () {
        $platform = mock(PlatformInterface::class);
        $platform->shouldReceive('getName')->andReturn('MySQL');

        $adapter = mock(ExtendedAdapterInterface::class);
        $adapter->shouldReceive('getPrefix')->andReturn('smf_');
        $adapter->shouldReceive('getPlatform')->andReturn($platform);
        $adapter->shouldReceive('getTitle')->andReturn('MySQL');

        $driver = mock(DriverInterface::class);
        $driver->shouldReceive('getDatabasePlatformName')->andReturn('MySQL');
        $adapter->shouldReceive('getDriver')->andReturn($driver);

        $metadata = mock('Laminas\Db\Metadata\MetadataInterface');
        $metadata->shouldReceive('getTableNames')->andThrow(new Exception('Metadata error'));

        $sql = new ExtendedSql($adapter, $metadata);

        expect($sql->tableExists('users'))->toBeFalse();
    });

    it('columnExists returns false when getMetadata throws exception', function () {
        $platform = mock(PlatformInterface::class);
        $platform->shouldReceive('getName')->andReturn('MySQL');

        $adapter = mock(ExtendedAdapterInterface::class);
        $adapter->shouldReceive('getPrefix')->andReturn('smf_');
        $adapter->shouldReceive('getPlatform')->andReturn($platform);
        $adapter->shouldReceive('getTitle')->andReturn('MySQL');

        $driver = mock(DriverInterface::class);
        $driver->shouldReceive('getDatabasePlatformName')->andReturn('MySQL');
        $adapter->shouldReceive('getDriver')->andReturn($driver);

        $metadata = mock('Laminas\Db\Metadata\MetadataInterface');
        $metadata->shouldReceive('getColumnNames')->andThrow(new Exception('Metadata error'));

        $sql = new ExtendedSql($adapter, $metadata);

        expect($sql->columnExists('users', 'username'))->toBeFalse();
    });

    it('columnExists returns false when PRAGMA throws exception for SQLite', function () {
        $platform = mock(PlatformInterface::class);
        $platform->shouldReceive('getName')->andReturn('SQLite');

        $adapter = mock(ExtendedAdapterInterface::class);
        $adapter->shouldReceive('getPrefix')->andReturn('smf_');
        $adapter->shouldReceive('getPlatform')->andReturn($platform);
        $adapter->shouldReceive('getTitle')->andReturn('SQLite');

        $driver = mock(DriverInterface::class);
        $driver->shouldReceive('getDatabasePlatformName')->andReturn('SQLite');
        $adapter->shouldReceive('getDriver')->andReturn($driver);

        $result = mock(ResultInterface::class);
        $result->shouldReceive('execute')->andThrow(new Exception('PRAGMA error'));

        $adapter->shouldReceive('query')
            ->with('PRAGMA table_info(smf_users)', Adapter::QUERY_MODE_EXECUTE)
            ->andReturn($result);

        $sql = new ExtendedSql($adapter);

        expect($sql->columnExists('users', 'username'))->toBeFalse();
    });

    it('getMetadata calls MetadataFactory when metadata is not set', function () {
        $adapter = TestAdapterFactory::create();

        $sql = new ExtendedSql($adapter);

        $accessor = new ReflectionAccessor($sql);
        $metadata = $accessor->callMethod('getMetadata');

        expect($metadata)->toBeInstanceOf('Laminas\Db\Metadata\MetadataInterface');
    });
});

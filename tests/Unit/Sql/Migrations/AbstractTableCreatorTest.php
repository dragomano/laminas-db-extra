<?php

declare(strict_types=1);

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Adapter\Platform\Sqlite;
use Laminas\Db\Extra\Adapter\ExtendedAdapterInterface;
use Laminas\Db\Extra\Result\ExtendedResultInterface;
use Laminas\Db\Extra\Sql\Columns\AutoIncrementInteger;
use Laminas\Db\Extra\Sql\Columns\MediumInteger;
use Laminas\Db\Extra\Sql\Columns\MediumText;
use Laminas\Db\Extra\Sql\Columns\SmallInteger;
use Laminas\Db\Extra\Sql\Columns\TinyInteger;
use Laminas\Db\Extra\Sql\Columns\UnsignedInteger;
use Laminas\Db\Extra\Sql\ExtendedSqlInterface;
use Laminas\Db\Extra\Sql\ExtendedTable;
use Laminas\Db\Extra\Sql\Migrations\AbstractTableCreator;
use Laminas\Db\Extra\Sql\Operations\ExtendedInsert;
use Laminas\Db\Extra\Sql\Operations\ExtendedSelect;
use Laminas\Db\Sql\Ddl\Column\Varchar;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\SqlInterface;
use Tests\ReflectionAccessor;

describe('AbstractTableCreator', function () {
    beforeEach(function () {
        $this->adapter = mock(ExtendedAdapterInterface::class);
        $this->adapter
            ->shouldReceive('getPlatform')
            ->andReturnUsing(function () {
                $platformMock = mock(PlatformInterface::class);
                $platformMock->shouldReceive('getName')->andReturn('MySQL');
                $platformMock->shouldReceive('quoteIdentifier')->andReturnUsing(fn($x) => $x);
                $platformMock->shouldReceive('quoteIdentifierChain')->andReturnUsing(fn($x) => $x);

                return $platformMock;
            });
        $this->adapter->shouldReceive('getTitle')->andReturn('MySQL')->byDefault();
        $this->adapter->shouldReceive('getCurrentSchema')->andReturn(null);

        $this->sql = mock('alias:AbstractTableCreatorSql', ExtendedSqlInterface::class);
        $this->sql->shouldReceive('getPrefix')->andReturn('smf_');
        $this->sql->shouldReceive('getAdapter')->andReturn($this->adapter);

        $this->testClass = new class ($this->sql) extends AbstractTableCreator {
            protected string $tableName = 'test_table';

            protected function defineColumns(ExtendedTable $table): void
            {
                $id      = new AutoIncrementInteger('id');
                $name    = new Varchar('name', 100);
                $content = new MediumText('content');
                $count   = new UnsignedInteger('count');
                $level   = new TinyInteger('level');
                $size    = new SmallInteger('size');
                $data    = new MediumInteger('data');

                $table->addColumn($id);
                $table->addColumn($name);
                $table->addColumn($content);
                $table->addColumn($count);
                $table->addColumn($level);
                $table->addColumn($size);
                $table->addColumn($data);
            }

            protected function getDefaultData(): array
            {
                return [];
            }
        };

        $this->creator = new (get_class($this->testClass))($this->sql);
    });

    dataset('insert scenarios', [
        [['id' => 1], 0, true],
        [['id' => 1], 1, false],
    ]);

    it('constructs with adapter and sql', function () {
        expect($this->creator)->toBeInstanceOf(AbstractTableCreator::class)
            ->and($this->creator)->toBeInstanceOf($this->testClass::class);
    });

    it('returns correct full table name', function () {
        $accessor = new ReflectionAccessor($this->testClass);

        $result = $accessor->callMethod('getFullTableName', [$this->creator]);

        expect($result)->toBe('smf_test_table');
    });

    it('executes sql', function () {
        $builder = mock(SqlInterface::class);
        $this->sql->shouldReceive('buildSqlString')->with($builder)->andReturn('SELECT 1');
        $this->adapter->shouldReceive('query')->with('SELECT 1', Adapter::QUERY_MODE_EXECUTE)->once();

        $accessor = new ReflectionAccessor($this->creator);
        $accessor->callMethod('executeSql', [$builder]);
    });

    it('creates table when it does not exist', function () {
        $this->sql->shouldReceive('tableExists')->with('test_table')->andReturn(false);
        $this->sql->shouldReceive('buildSqlString')
            ->andReturn(/** @lang text */ 'CREATE TABLE smf_test_table (id INT UNSIGNED AUTO_INCREMENT, name VARCHAR(100), content MEDIUMTEXT, count INT UNSIGNED, level TINYINT(4), size SMALLINT(6), data MEDIUMINT)');
        $this->adapter->shouldReceive('query')->andReturn(null);

        $this->creator->createTable();

        expect(true)->toBeTrue();
    });

    it('does not create table when it exists', function () {
        $this->sql->shouldReceive('tableExists')->with('test_table')->andReturn(true);

        $this->creator->createTable();

        expect(true)->toBeTrue();
    });

    it('returns correct sql string', function () {
        $this->sql->shouldReceive('buildSqlString')
            ->andReturn(/** @lang text */ 'CREATE TABLE smf_test_table (id INT UNSIGNED AUTO_INCREMENT, name VARCHAR(100), content MEDIUMTEXT, count INT UNSIGNED, level TINYINT(4), size SMALLINT(6), data MEDIUMINT)');

        $result = $this->creator->getSql();

        expect($result)
            ->toBe(/** @lang text */ 'CREATE TABLE smf_test_table (id INT UNSIGNED AUTO_INCREMENT, name VARCHAR(100), content MEDIUMTEXT, count INT UNSIGNED, level TINYINT(4), size SMALLINT(6), data MEDIUMINT)');
    });

    it('handles dropping table based on existence', function ($expected) {
        $this->sql->shouldReceive('tableExists')->with('test_table')->andReturn($expected);

        if ($expected) {
            $this->sql->shouldReceive('buildSqlString')->andReturn(/** @lang text */ 'DROP TABLE smf_test_table');
            $this->adapter->shouldReceive('query')->andReturn(null);
        }

        $this->creator->dropTable();

        expect(true)->toBeTrue();
    })->with([true, false]);

    it('handles inserting default data based on existence', function ($where, $count, $shouldInsert) {
        $select = mock(ExtendedSelect::class);
        $select->shouldReceive('where')->with($where)->andReturnSelf();
        $select->shouldReceive('columns')
            ->with(['count' => new Expression('COUNT(*)')], false)
            ->andReturnSelf();

        $this->sql->shouldReceive('select')->with('test_table')->andReturn($select);
        $resultMock = mock(ExtendedResultInterface::class);
        $resultMock->shouldReceive('current')->andReturn(['count' => $count]);
        $this->sql->shouldReceive('execute')->with($select)->andReturn($resultMock);

        if ($shouldInsert) {
            $insert = mock(ExtendedInsert::class);
            $insert->shouldReceive('columns')->with(['id', 'name'])->andReturnSelf();
            $insert->shouldReceive('values')->with([1, 'test'])->andReturnSelf();

            $this->sql->shouldReceive('insert')->with('test_table')->andReturn($insert);
            $this->sql->shouldReceive('execute')->with($insert);
        } else {
            $this->sql->shouldNotReceive('insert');
        }

        $accessor = new ReflectionAccessor($this->creator);
        $accessor->callMethod('insertDefaultIfNotExists', [$where, ['id', 'name'], [1, 'test']]);

        expect(true)->toBeTrue();
    })->with('insert scenarios');

    it('inserts default data method is empty', function () {
        $this->creator->insertDefaultData();

        expect(true)->toBeTrue();
    });

    it('inserts default data when data is provided', function () {
        $testClass = new class ($this->sql) extends AbstractTableCreator {
            protected string $tableName = 'test_table';

            protected function defineColumns(ExtendedTable $table): void {}

            protected function getDefaultData(): array
            {
                return [['id' => 1], ['id', 'name'], [1, 'test']];
            }
        };

        $creator = new (get_class($testClass))($this->sql);

        $select = mock(ExtendedSelect::class);
        $select->shouldReceive('where')->andReturnSelf();
        $select->shouldReceive('columns')->andReturnSelf();

        $this->sql->shouldReceive('select')->with('test_table')->andReturn($select);
        $resultMock = mock(ExtendedResultInterface::class);
        $resultMock->shouldReceive('current')->andReturn(['count' => 0]);
        $this->sql->shouldReceive('execute')->with($select)->andReturn($resultMock);

        $insert = mock(ExtendedInsert::class);
        $insert->shouldReceive('columns')->andReturnSelf();
        $insert->shouldReceive('values')->andReturnSelf();

        $this->sql->shouldReceive('insert')->with('test_table')->andReturn($insert);
        $this->sql->shouldReceive('execute')->with($insert);

        $creator->insertDefaultData();

        expect(true)->toBeTrue();
    });

    it('handles multiple values in insertDefaultIfNotExists', function () {
        $select = mock(ExtendedSelect::class);
        $select->shouldReceive('where')->with(['status' => 'active'])->andReturnSelf();
        $select->shouldReceive('columns')
            ->with(['count' => new Expression('COUNT(*)')], false)
            ->andReturnSelf();

        $this->sql->shouldReceive('select')->with('test_table')->andReturn($select);
        $resultMock = mock(ExtendedResultInterface::class);
        $resultMock->shouldReceive('current')->andReturn(['count' => 0]);
        $this->sql->shouldReceive('execute')->with($select)->andReturn($resultMock);

        $insert = mock(ExtendedInsert::class);
        $insert->shouldReceive('columns')->with(['id', 'name'])->andReturnSelf();
        $insert->shouldReceive('values')->with([1, 'first'])->andReturnSelf();
        $insert->shouldReceive('values')->with([2, 'second'])->andReturnSelf();

        $this->sql->shouldReceive('insert')->with('test_table')->andReturn($insert);
        $this->sql->shouldReceive('execute')->with($insert)->twice();

        $accessor = new ReflectionAccessor($this->creator);
        $accessor->callMethod('insertDefaultIfNotExists', [
            ['status' => 'active'],
            ['id', 'name'],
            [[1, 'first'], [2, 'second']],
        ]);

        expect(true)->toBeTrue();
    });

    it('creates table with indexes', function () {
        $this->sql->shouldReceive('tableExists')->with('test_table')->andReturn(false);
        $this->sql->shouldReceive('buildSqlString')
            ->andReturn(/** @lang text */ 'CREATE TABLE smf_test_table (id INT)');
        $this->adapter->shouldReceive('query')->andReturn(null);

        $platform = mock(PlatformInterface::class);
        $platform->shouldReceive('getName')->andReturn('MySQL');
        $this->adapter->shouldReceive('getPlatform')->andReturn($platform);

        $testClass = new class ($this->sql) extends AbstractTableCreator {
            protected string $tableName = 'test_table';

            protected function defineColumns(ExtendedTable $table): void
            {
                $table->addColumn(new Varchar('name', 100));
                $table->addIndex('idx_name', ['name']);
            }

            protected function getDefaultData(): array
            {
                return [];
            }
        };

        $creator = new (get_class($testClass))($this->sql);
        $creator->createTable();

        expect(true)->toBeTrue();
    });

    it('creates table with indexes for SQLite', function () {
        $platform = new Sqlite();

        $adapter = mock(ExtendedAdapterInterface::class);
        $adapter->shouldReceive('getPlatform')->andReturn($platform);
        $adapter->shouldReceive('query')
            ->withArgs(function ($sql, $mode) {
                return str_contains($sql, 'CREATE TABLE') && $mode === Adapter::QUERY_MODE_EXECUTE;
            })
            ->once();
        $adapter->shouldReceive('query')
            ->withArgs(function ($sql, $mode) {
                return str_contains($sql, 'CREATE INDEX') && $mode === Adapter::QUERY_MODE_EXECUTE;
            })
            ->once();

        $sql = mock(ExtendedSqlInterface::class);
        $sql->shouldReceive('getPrefix')->andReturn('smf_');
        $sql->shouldReceive('getAdapter')->andReturn($adapter);
        $sql->shouldReceive('tableExists')->with('test_table')->andReturn(false);
        $sql->shouldReceive('buildSqlString')
            ->andReturnUsing(function ($table) use ($platform) {
                return $table->getSqlString($platform);
            });

        $testClass = new class ($sql) extends AbstractTableCreator {
            protected string $tableName = 'test_table';

            protected function defineColumns(ExtendedTable $table): void
            {
                $table->addColumn(new Varchar('name', 100));
                $table->addIndex('idx_name', ['name']);
            }

            protected function getDefaultData(): array
            {
                return [];
            }
        };

        $creator = new (get_class($testClass))($sql);
        $creator->createTable();

        expect(true)->toBeTrue();
    });
});

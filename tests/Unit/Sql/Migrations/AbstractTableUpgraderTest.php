<?php

declare(strict_types=1);

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Extra\Adapter\ExtendedAdapterInterface;
use Laminas\Db\Extra\Sql\Columns\MediumText;
use Laminas\Db\Extra\Sql\ExtendedSql;
use Laminas\Db\Extra\Sql\Migrations\AbstractTableUpgrader;
use Laminas\Db\ResultSet\ResultSetInterface;
use Laminas\Db\Sql\Ddl\Column\Column;
use Laminas\Db\Sql\Ddl\Column\Integer;
use Laminas\Db\Sql\Ddl\Column\Varchar;
use Laminas\Db\Sql\SqlInterface;
use Tests\ReflectionAccessor;

describe('AbstractTableUpgrader', function () {
    beforeEach(function () {
        $this->adapter = mock(ExtendedAdapterInterface::class);
        $this->adapter->shouldReceive('getDriver')->andReturn(mock());
        $this->adapter->shouldReceive('getTitle')->andReturn('MySQL');

        $this->platform = mock(PlatformInterface::class);

        $this->sql = mock(ExtendedSql::class);
        $this->sql->shouldReceive('getAdapter')->andReturn($this->adapter);

        $this->upgrader = new class ($this->sql) extends AbstractTableUpgrader {
            protected string $tableName = 'old_table';

            public function upgrade(): void {}
        };
    });

    it('defines varchar column correctly', function () {
        $upgrader = new ReflectionAccessor($this->upgrader);

        $column = $upgrader->callMethod('defineColumn', [
            'test_column',
            [
                'type'     => 'varchar',
                'size'     => 100,
                'nullable' => true,
                'default'  => 'default_value',
            ],
        ]);

        expect($column)->toBeInstanceOf(Varchar::class)
            ->and($column->getName())->toBe('test_column');
    });

    it('defines int column correctly', function () {
        $upgrader = new ReflectionAccessor($this->upgrader);

        $column = $upgrader->callMethod('defineColumn', [
            'test_int',
            [
                'type'     => 'int',
                'size'     => 10,
                'nullable' => false,
                'default'  => 0,
            ],
        ]);

        expect($column)->toBeInstanceOf(Integer::class)
            ->and($column->getName())->toBe('test_int');
    });

    it('gets full table name with prefix', function () {
        $this->sql->shouldReceive('getPrefix')->andReturn('smf_');

        $upgrader = new ReflectionAccessor($this->upgrader);

        $fullName = $upgrader->callMethod('getFullTableName', ['test_table']);

        expect($fullName)->toBe('smf_test_table');
    });

    it('executes sql query', function () {
        $builder = mock(SqlInterface::class);
        $sqlString = 'SELECT 1';

        $this->sql->shouldReceive('buildSqlString')->with($builder)->andReturn($sqlString);
        $this->adapter->shouldReceive('query')->with($sqlString, Adapter::QUERY_MODE_EXECUTE);

        $upgrader = new ReflectionAccessor($this->upgrader);
        $upgrader->callMethod('executeSql', [$builder]);

        expect(true)->toBeTrue();
    });

    it('adds index', function () {
        $this->platform->shouldReceive('quoteIdentifier')->andReturn('`test_index`');

        $this->adapter->shouldReceive('getPlatform')->andReturn($this->platform);
        $this->adapter->shouldReceive('getTitle')->andReturn('mysql');
        $this->sql->shouldReceive('getPrefix')->andReturn('smf_');

        $resultMock = mock(ResultSetInterface::class);
        $resultMock->shouldReceive('toArray')->andReturn([]);

        $this->adapter
            ->shouldReceive('query')
            ->with("SHOW INDEX FROM smf_old_table WHERE Key_name = 'test_index'", Adapter::QUERY_MODE_EXECUTE)
            ->andReturn($resultMock)
            ->shouldReceive('query')
            ->with(/** @lang text */ 'CREATE INDEX `test_index` ON smf_old_table (col1, col2)', Adapter::QUERY_MODE_EXECUTE);

        $upgrader = new ReflectionAccessor($this->upgrader);
        $upgrader->callMethod('addIndex', ['test_index', ['col1', 'col2']]);

        expect(true)->toBeTrue();
    });

    it('adds prefix index', function () {
        $this->platform->shouldReceive('quoteIdentifier')
            ->with('test_index')
            ->andReturn('`test_index`');
        $this->adapter->shouldReceive('getPlatform')->andReturn($this->platform);
        $this->adapter->shouldReceive('getTitle')->andReturn('mysql');

        $this->sql->shouldReceive('getPrefix')->andReturn('smf_');

        $resultMock = mock(ResultSetInterface::class);
        $resultMock->shouldReceive('toArray')->andReturn([]);

        $this->adapter->shouldReceive('query')
            ->with(Mockery::on(function ($sql) {
                return str_contains($sql, 'SHOW INDEX FROM') || str_contains($sql, 'CREATE INDEX');
            }), Adapter::QUERY_MODE_EXECUTE)
            ->andReturnUsing(function ($sql) use ($resultMock) {
                if (str_contains($sql, 'SHOW INDEX FROM')) {
                    return $resultMock;
                }

                return null;
            });

        $upgrader = new ReflectionAccessor($this->upgrader);
        $upgrader->callMethod('addPrefixIndex', ['test_index', 'col1', 10]);

        expect(true)->toBeTrue();
    });

    it('renames table', function () {
        $this->sql->shouldReceive('getPrefix')->andReturn('smf_');
        $this->adapter
            ->shouldReceive('query')
            ->with(/** @lang text */ 'ALTER TABLE smf_old_table RENAME TO smf_new_table', Adapter::QUERY_MODE_EXECUTE);

        $upgrader = new ReflectionAccessor($this->upgrader);
        $upgrader->callMethod('renameTable', ['new_table']);

        expect(true)->toBeTrue();
    });

    it('alters column by adding', function () {
        $this->sql->shouldReceive('columnExists')->with('old_table', 'test_column')->andReturn(false);
        $this->sql->shouldReceive('getPrefix')->andReturn('smf_');

        $sqlString = /** @lang text */ 'ALTER TABLE smf_old_table ADD test_column VARCHAR(255) NOT NULL';
        $this->sql->shouldReceive('buildSqlString')->andReturn($sqlString);
        $this->adapter->shouldReceive('query')->with($sqlString, Adapter::QUERY_MODE_EXECUTE);

        $upgrader = new ReflectionAccessor($this->upgrader);
        $upgrader->callMethod('alterColumn', ['add', 'test_column', ['type' => 'varchar']]);

        expect(true)->toBeTrue();
    });

    it('alters column by changing', function () {
        $this->sql->shouldReceive('columnExists')->with('old_table', 'old_column')->andReturn(false);
        $this->sql->shouldReceive('getPrefix')->andReturn('smf_');

        $sqlString = /** @lang text */ 'ALTER TABLE smf_old_table CHANGE old_column new_column INT(11) NOT NULL DEFAULT 0';
        $this->sql->shouldReceive('buildSqlString')->andReturn($sqlString);
        $this->adapter->shouldReceive('query')->with($sqlString, Adapter::QUERY_MODE_EXECUTE);

        $upgrader = new ReflectionAccessor($this->upgrader);
        $upgrader->callMethod('alterColumn', ['change', 'old_column', ['type' => 'int'], 'new_column']);

        expect(true)->toBeTrue();
    });

    it('alters column by dropping', function () {
        $this->sql->shouldReceive('columnExists')->with('old_table', 'test_column')->andReturn(false);
        $this->sql->shouldReceive('getPrefix')->andReturn('smf_');

        $sqlString = /** @lang text */ 'ALTER TABLE smf_old_table DROP COLUMN test_column';
        $this->sql->shouldReceive('buildSqlString')->andReturn($sqlString);
        $this->adapter->shouldReceive('query')->with($sqlString, Adapter::QUERY_MODE_EXECUTE);

        $upgrader = new ReflectionAccessor($this->upgrader);
        $upgrader->callMethod('alterColumn', ['drop', 'test_column']);

        expect(true)->toBeTrue();
    });

    it('does not alter column if it exists', function () {
        $this->sql->shouldReceive('columnExists')->with('old_table', 'test_column')->andReturn(true);

        $this->sql->shouldNotReceive('buildSqlString');
        $this->adapter->shouldNotReceive('query');

        $upgrader = new ReflectionAccessor($this->upgrader);
        $upgrader->callMethod('alterColumn', ['add', 'test_column']);

        expect(true)->toBeTrue();
    });

    it('defines column with default value', function () {
        $upgrader = new ReflectionAccessor($this->upgrader);

        $column = $upgrader->callMethod('defineColumn', [
            'test_column',
            [
                'type'     => 'varchar',
                'size'     => 100,
                'nullable' => true,
                'default'  => 'test_default',
            ],
        ]);

        expect($column->getDefault())->toBe('test_default');
    });

    it('defines column with text type using default match case', function () {
        $upgrader = new ReflectionAccessor($this->upgrader);

        $column = $upgrader->callMethod('defineColumn', [
            'text_column',
            [
                'type'     => 'text',
                'nullable' => false,
                'default'  => null,
            ],
        ]);

        expect($column)->toBeInstanceOf(Column::class)
            ->and($column->getName())->toBe('text_column')
            ->and($column->getOptions()['type'])->toBe('text');
    });

    it('drops column in sqlite with default value containing parentheses', function () {
        $this->adapter->shouldReceive('getTitle')->andReturn('SQLite');
        $this->sql->shouldReceive('getPrefix')->andReturn('smf_');
        $this->sql->shouldReceive('getAdapter')->andReturn($this->adapter);

        $columnsData = [
            ['name' => 'id', 'type' => 'INTEGER', 'notnull' => 1, 'dflt_value' => null, 'pk' => 1],
            ['name' => 'name', 'type' => 'VARCHAR(255)', 'notnull' => 1, 'dflt_value' => '1', 'pk' => 0],
            ['name' => 'status', 'type' => 'INTEGER', 'notnull' => 0, 'dflt_value' => null, 'pk' => 0],
        ];

        $this->adapter->shouldReceive('query')
            ->with('PRAGMA table_info(smf_old_table)', Adapter::QUERY_MODE_EXECUTE)
            ->andReturn(new ArrayIterator($columnsData));

        $this->adapter->shouldReceive('query')
            ->with(Mockery::on(function ($sql) {
                if (str_contains($sql, 'CREATE TABLE')) {
                    return str_contains($sql, 'id INTEGER NOT NULL PRIMARY KEY')
                        && str_contains($sql, 'status INTEGER');
                }

                if (str_contains($sql, 'INSERT INTO')) {
                    return str_contains($sql, /** @lang text */ 'SELECT id, status FROM smf_old_table');
                }

                if (str_contains($sql, 'DROP TABLE')) {
                    return str_contains($sql, /** @lang text */ 'DROP TABLE smf_old_table');
                }

                if (str_contains($sql, 'ALTER TABLE')) {
                    return str_contains($sql, 'RENAME TO old_table');
                }

                return false;
            }), Adapter::QUERY_MODE_EXECUTE);

        $upgrader = new ReflectionAccessor($this->upgrader);
        $upgrader->callMethod('dropColumnSqlite', ['name']);

        expect(true)->toBeTrue();
    });

    dataset('column definitions', [
        ['test_column', ['type' => 'varchar', 'size' => 100, 'nullable' => true, 'default' => 'default_value'], Varchar::class],
        ['test_int', ['type' => 'int', 'size' => 10, 'nullable' => false, 'default' => 0], Integer::class],
        ['test_content', ['type' => 'mediumtext', 'nullable' => true], MediumText::class],
        ['test_auto_increment', ['type' => 'integer', 'auto_increment' => true], Column::class],
        ['test_tinyint', ['type' => 'tinyint', 'size' => 4], Column::class],
        ['test_smallint', ['type' => 'smallint', 'size' => 6], Column::class],
        ['test_mediumint', ['type' => 'mediumint', 'size' => 8], Column::class],
        ['test_unsigned_int', ['type' => 'integer', 'unsigned' => true], Column::class],
    ]);

    it('defines columns correctly', function ($columnName, $params, $expectedClass) {
        $upgrader = new ReflectionAccessor($this->upgrader);

        $column = $upgrader->callMethod('defineColumn', [$columnName, $params]);

        expect($column)->toBeInstanceOf($expectedClass)
            ->and($column->getName())->toBe($columnName);
    })->with('column definitions');

    it('does not update table when it does not exist', function () {
        $this->sql->shouldReceive('tableExists')->with('old_table')->andReturn(false);

        $this->upgrader->updateTable();

        expect(true)->toBeTrue();
    });

    it('calls upgrade when table exists', function () {
        $this->sql->shouldReceive('tableExists')->with('old_table')->andReturn(true);

        $upgrader = new class ($this->sql) extends AbstractTableUpgrader {
            protected string $tableName = 'old_table';

            public bool $upgradeCalled = false;

            public function upgrade(): void
            {
                $this->upgradeCalled = true;
            }
        };

        $upgrader->updateTable();

        expect($upgrader->upgradeCalled)->toBeTrue();
    });

    it('removes integer size for PostgreSQL', function () {
        $builder = mock(SqlInterface::class);
        $sqlString /** @lang text */
            = 'ALTER TABLE test_table ADD COLUMN id INTEGER(11) NOT NULL';

        $postgresAdapter = mock(ExtendedAdapterInterface::class);
        $postgresAdapter->shouldReceive('getDriver')->andReturn(mock());
        $postgresAdapter->shouldReceive('getTitle')->andReturn('PostgreSQL');
        $postgresAdapter->shouldReceive('query')
            ->with(/** @lang text */ 'ALTER TABLE test_table ADD COLUMN id INTEGER NOT NULL', Adapter::QUERY_MODE_EXECUTE);

        $sql = mock(ExtendedSql::class);
        $sql->shouldReceive('getAdapter')->andReturn($postgresAdapter);
        $sql->shouldReceive('buildSqlString')->with($builder)->andReturn($sqlString);

        $upgrader = new class ($sql) extends AbstractTableUpgrader {
            protected string $tableName = 'old_table';

            public function upgrade(): void {}
        };

        $upgraderAccessor = new ReflectionAccessor($upgrader);
        $upgraderAccessor->callMethod('executeSql', [$builder]);

        expect(true)->toBeTrue();
    });

    it('removes bigint size for PostgreSQL', function () {
        $builder = mock(SqlInterface::class);
        $sqlString /** @lang text */
            = 'ALTER TABLE test_table ADD COLUMN id BIGINT(20) NOT NULL';

        $postgresAdapter = mock(ExtendedAdapterInterface::class);
        $postgresAdapter->shouldReceive('getDriver')->andReturn(mock());
        $postgresAdapter->shouldReceive('getTitle')->andReturn('PostgreSQL');
        $postgresAdapter->shouldReceive('query')
            ->with(/** @lang text */ 'ALTER TABLE test_table ADD COLUMN id BIGINT NOT NULL', Adapter::QUERY_MODE_EXECUTE);

        $sql = mock(ExtendedSql::class);
        $sql->shouldReceive('getAdapter')->andReturn($postgresAdapter);
        $sql->shouldReceive('buildSqlString')->with($builder)->andReturn($sqlString);

        $upgrader = new class ($sql) extends AbstractTableUpgrader {
            protected string $tableName = 'old_table';

            public function upgrade(): void {}
        };

        $upgraderAccessor = new ReflectionAccessor($upgrader);
        $upgraderAccessor->callMethod('executeSql', [$builder]);

        expect(true)->toBeTrue();
    });

    it('renames column when changing with different name', function () {
        $this->sql->shouldReceive('columnExists')->with('old_table', 'old_col')->andReturn(true);
        $this->sql->shouldReceive('getPrefix')->andReturn('smf_');

        $this->platform->shouldReceive('quoteIdentifier')->andReturnUsing(fn($x) => $x);
        $this->adapter->shouldReceive('getPlatform')->andReturn($this->platform);

        $this->adapter->shouldReceive('query')
            ->with(/** @lang text */ 'ALTER TABLE smf_old_table RENAME COLUMN old_col TO new_col', Adapter::QUERY_MODE_EXECUTE);
        $this->sql->shouldReceive('buildSqlString')->andReturn(/** @lang text */ 'ALTER TABLE smf_old_table CHANGE new_col INT');
        $this->adapter->shouldReceive('query')
            ->with(/** @lang text */ 'ALTER TABLE smf_old_table CHANGE new_col INT', Adapter::QUERY_MODE_EXECUTE);

        $upgrader = new ReflectionAccessor($this->upgrader);
        $upgrader->callMethod('alterColumn', ['change', 'old_col', ['type' => 'int'], 'new_col']);

        expect(true)->toBeTrue();
    });

    it('does not change column when params empty', function () {
        $this->sql->shouldReceive('columnExists')->with('old_table', 'test_col')->andReturn(true);
        $this->sql->shouldReceive('getPrefix')->andReturn('smf_');

        $upgrader = new ReflectionAccessor($this->upgrader);
        $upgrader->callMethod('alterColumn', ['change', 'test_col', [], 'test_col']);

        expect(true)->toBeTrue();
    });

    it('changes column without rename when names are same', function () {
        $this->sql->shouldReceive('columnExists')->with('old_table', 'col')->andReturn(true);
        $this->sql->shouldReceive('getPrefix')->andReturn('smf_');
        $this->sql->shouldReceive('buildSqlString')->andReturn(/** @lang text */ 'ALTER TABLE smf_old_table CHANGE col INT');
        $this->adapter->shouldReceive('query');

        $upgrader = new ReflectionAccessor($this->upgrader);
        $upgrader->callMethod('alterColumn', ['change', 'col', ['type' => 'int'], 'col']);

        expect(true)->toBeTrue();
    });

    it('drops existing column', function () {
        $this->sql->shouldReceive('columnExists')->with('old_table', 'test_column')->andReturn(true);
        $this->sql->shouldReceive('getPrefix')->andReturn('smf_');

        $sqlString = /** @lang text */ 'ALTER TABLE smf_old_table DROP COLUMN test_column';
        $this->sql->shouldReceive('buildSqlString')->andReturn($sqlString);
        $this->adapter->shouldReceive('query')->with($sqlString, Adapter::QUERY_MODE_EXECUTE);

        $upgrader = new ReflectionAccessor($this->upgrader);
        $upgrader->callMethod('alterColumn', ['drop', 'test_column']);

        expect(true)->toBeTrue();
    });

    it('adds column using addColumn method', function () {
        $this->sql->shouldReceive('columnExists')->with('old_table', 'new_column')->andReturn(false);
        $this->sql->shouldReceive('getPrefix')->andReturn('smf_');

        $sqlString = /** @lang text */ 'ALTER TABLE smf_old_table ADD new_column VARCHAR(255)';
        $this->sql->shouldReceive('buildSqlString')->andReturn($sqlString);
        $this->adapter->shouldReceive('query')->with($sqlString, Adapter::QUERY_MODE_EXECUTE);

        $upgrader = new ReflectionAccessor($this->upgrader);
        $upgrader->callMethod('addColumn', ['new_column', ['type' => 'varchar']]);

        expect(true)->toBeTrue();
    });

    it('changes column using changeColumn method', function () {
        $this->sql->shouldReceive('columnExists')->with('old_table', 'old_col')->andReturn(true);
        $this->sql->shouldReceive('getPrefix')->andReturn('smf_');

        $this->platform->shouldReceive('quoteIdentifier')->andReturnUsing(fn($x) => $x);
        $this->adapter->shouldReceive('getPlatform')->andReturn($this->platform);

        $this->adapter->shouldReceive('query')
            ->with(/** @lang text */ 'ALTER TABLE smf_old_table RENAME COLUMN old_col TO new_col', Adapter::QUERY_MODE_EXECUTE);
        $this->sql->shouldReceive('buildSqlString')->andReturn(/** @lang text */ 'ALTER TABLE smf_old_table CHANGE new_col INT');
        $this->adapter->shouldReceive('query')
            ->with(/** @lang text */ 'ALTER TABLE smf_old_table CHANGE new_col INT', Adapter::QUERY_MODE_EXECUTE);

        $upgrader = new ReflectionAccessor($this->upgrader);
        $upgrader->callMethod('changeColumn', ['old_col', 'new_col', ['type' => 'int']]);

        expect(true)->toBeTrue();
    });

    it('drops column using dropColumn method for MySQL', function () {
        $this->sql->shouldReceive('columnExists')->with('old_table', 'test_column')->andReturn(true);
        $this->sql->shouldReceive('getPrefix')->andReturn('smf_');
        $this->adapter->shouldReceive('getTitle')->andReturn('MySQL');

        $sqlString = /** @lang text */ 'ALTER TABLE smf_old_table DROP COLUMN test_column';
        $this->sql->shouldReceive('buildSqlString')->andReturn($sqlString);
        $this->adapter->shouldReceive('query')->with($sqlString, Adapter::QUERY_MODE_EXECUTE);

        $upgrader = new ReflectionAccessor($this->upgrader);
        $upgrader->callMethod('dropColumn', ['test_column']);

        expect(true)->toBeTrue();
    });

    it('drops column using dropColumn method for SQLite and calls dropColumnSqlite', function () {
        $sqliteAdapter = mock(ExtendedAdapterInterface::class);
        $sqliteAdapter->shouldReceive('getDriver')->andReturn(mock());
        $sqliteAdapter->shouldReceive('getTitle')->andReturn('SQLite');

        $sqliteSql = mock(ExtendedSql::class);
        $sqliteSql->shouldReceive('getAdapter')->andReturn($sqliteAdapter);
        $sqliteSql->shouldReceive('getPrefix')->andReturn('smf_');

        $columnsData = [
            ['name' => 'id', 'type' => 'INTEGER', 'notnull' => 1, 'dflt_value' => null, 'pk' => 1],
            ['name' => 'test_column', 'type' => 'VARCHAR(255)', 'notnull' => 1, 'dflt_value' => null, 'pk' => 0],
        ];

        $sqliteAdapter->shouldReceive('query')
            ->with('PRAGMA table_info(smf_old_table)', Adapter::QUERY_MODE_EXECUTE)
            ->andReturn(new ArrayIterator($columnsData));

        $sqliteAdapter->shouldReceive('query')
            ->with(Mockery::on(function ($sql) {
                return str_contains($sql, 'CREATE TABLE')
                    || str_contains($sql, 'INSERT INTO')
                    || str_contains($sql, 'DROP TABLE')
                    || str_contains($sql, 'ALTER TABLE');
            }), Adapter::QUERY_MODE_EXECUTE);

        $sqliteSql->shouldReceive('columnExists')->with('old_table', 'test_column')->andReturn(true);
        $sqliteSql->shouldReceive('buildSqlString')->andReturn(/** @lang text */ 'ALTER TABLE smf_old_table DROP COLUMN test_column');

        $upgrader = new class ($sqliteSql) extends AbstractTableUpgrader {
            protected string $tableName = 'old_table';

            public function upgrade(): void {}
        };

        $upgraderAccessor = new ReflectionAccessor($upgrader);
        $upgraderAccessor->callMethod('dropColumn', ['test_column']);

        expect(true)->toBeTrue();
    });

    it('drops column using dropColumn method for SQLite', function () {
        $this->adapter->shouldReceive('getTitle')->andReturn('SQLite');
        $this->sql->shouldReceive('getPrefix')->andReturn('smf_');
        $this->sql->shouldReceive('getAdapter')->andReturn($this->adapter);

        $columnsData = [
            ['name' => 'id', 'type' => 'INTEGER', 'notnull' => 1, 'dflt_value' => null, 'pk' => 1],
            ['name' => 'test_column', 'type' => 'VARCHAR(255)', 'notnull' => 1, 'dflt_value' => null, 'pk' => 0],
        ];

        $this->adapter->shouldReceive('query')
            ->with('PRAGMA table_info(smf_old_table)', Adapter::QUERY_MODE_EXECUTE)
            ->andReturn(new ArrayIterator($columnsData));

        $this->adapter->shouldReceive('query')
            ->with(Mockery::on(function ($sql) {
                return str_contains($sql, 'CREATE TABLE')
                    || str_contains($sql, 'INSERT INTO')
                    || str_contains($sql, 'DROP TABLE')
                    || str_contains($sql, 'ALTER TABLE');
            }), Adapter::QUERY_MODE_EXECUTE);

        $this->sql->shouldReceive('columnExists')->with('old_table', 'test_column')->andReturn(true);
        $this->sql->shouldReceive('buildSqlString')->andReturn(/** @lang text */ 'ALTER TABLE smf_old_table DROP COLUMN test_column');

        $upgrader = new ReflectionAccessor($this->upgrader);
        $upgrader->callMethod('dropColumn', ['test_column']);

        expect(true)->toBeTrue();
    });

    it('adds prefix index for PostgreSQL', function () {
        $adapter = mock(ExtendedAdapterInterface::class);
        $adapter->shouldReceive('getDriver')->andReturn(mock());
        $adapter->shouldReceive('getTitle')->andReturn('PostgreSQL');

        $this->platform->shouldReceive('quoteIdentifier')->andReturn('"test_index"');
        $adapter->shouldReceive('getPlatform')->andReturn($this->platform);

        $sql = mock(ExtendedSql::class);
        $sql->shouldReceive('getAdapter')->andReturn($adapter);
        $sql->shouldReceive('getPrefix')->andReturn('smf_');

        $adapter->shouldReceive('query')
            ->with(Mockery::on(function ($sql) {
                return str_contains($sql, /** @lang text */ 'CREATE INDEX IF NOT EXISTS test_index ON smf_old_table (substring(col1 FROM 1 FOR 10))');
            }), Adapter::QUERY_MODE_EXECUTE);

        $upgrader = new class ($sql) extends AbstractTableUpgrader {
            protected string $tableName = 'old_table';

            public function upgrade(): void {}
        };

        $upgraderAccessor = new ReflectionAccessor($upgrader);
        $upgraderAccessor->callMethod('addPrefixIndex', ['test_index', 'col1', 10]);

        expect(true)->toBeTrue();
    });

    it('adds prefix index for SQLite', function () {
        $adapter = mock(ExtendedAdapterInterface::class);
        $adapter->shouldReceive('getDriver')->andReturn(mock());
        $adapter->shouldReceive('getTitle')->andReturn('SQLite');

        $this->platform->shouldReceive('quoteIdentifier')->andReturn('"test_index"');
        $adapter->shouldReceive('getPlatform')->andReturn($this->platform);

        $sql = mock(ExtendedSql::class);
        $sql->shouldReceive('getAdapter')->andReturn($adapter);
        $sql->shouldReceive('getPrefix')->andReturn('smf_');

        $adapter->shouldReceive('query')
            ->with(Mockery::on(function ($sql) {
                return str_contains($sql, /** @lang text */ 'CREATE INDEX IF NOT EXISTS test_index ON smf_old_table (substr(col1, 1, 10))');
            }), Adapter::QUERY_MODE_EXECUTE);

        $upgrader = new class ($sql) extends AbstractTableUpgrader {
            protected string $tableName = 'old_table';

            public function upgrade(): void {}
        };

        $upgraderAccessor = new ReflectionAccessor($upgrader);
        $upgraderAccessor->callMethod('addPrefixIndex', ['test_index', 'col1', 10]);

        expect(true)->toBeTrue();
    });

    it('drops column in sqlite with quoted default value', function () {
        $this->platform->shouldReceive('quoteValue')->with('default_value')->andReturn("'default_value'");
        $this->adapter->shouldReceive('getPlatform')->andReturn($this->platform);
        $this->adapter->shouldReceive('getTitle')->andReturn('SQLite');
        $this->sql->shouldReceive('getPrefix')->andReturn('smf_');
        $this->sql->shouldReceive('getAdapter')->andReturn($this->adapter);

        $columnsData = [
            ['name' => 'id', 'type' => 'INTEGER', 'notnull' => 1, 'dflt_value' => null, 'pk' => 1],
            ['name' => 'name', 'type' => 'VARCHAR(255)', 'notnull' => 1, 'dflt_value' => 'default_value', 'pk' => 0],
            ['name' => 'status', 'type' => 'INTEGER', 'notnull' => 0, 'dflt_value' => null, 'pk' => 0],
        ];

        $this->adapter->shouldReceive('query')
            ->with('PRAGMA table_info(smf_old_table)', Adapter::QUERY_MODE_EXECUTE)
            ->andReturn(new ArrayIterator($columnsData));

        $this->adapter->shouldReceive('query')
            ->with(Mockery::on(function ($sql) {
                if (str_contains($sql, 'CREATE TABLE')) {
                    return str_contains($sql, "name VARCHAR(255) NOT NULL DEFAULT 'default_value'");
                }

                if (str_contains($sql, 'INSERT INTO')) {
                    return str_contains($sql, /** @lang text */ 'SELECT id, name, status FROM smf_old_table');
                }

                if (str_contains($sql, 'DROP TABLE')) {
                    return str_contains($sql, /** @lang text */ 'DROP TABLE smf_old_table');
                }

                if (str_contains($sql, 'ALTER TABLE')) {
                    return str_contains($sql, 'RENAME TO old_table');
                }

                return false;
            }), Adapter::QUERY_MODE_EXECUTE);

        $upgrader = new ReflectionAccessor($this->upgrader);
        $upgrader->callMethod('dropColumnSqlite', ['test_column']);

        expect(true)->toBeTrue();
    });

    it('creates index if not exists for PostgreSQL', function () {
        $adapter = mock(ExtendedAdapterInterface::class);
        $adapter->shouldReceive('getDriver')->andReturn(mock());
        $adapter->shouldReceive('getTitle')->andReturn('PostgreSQL');

        $this->platform->shouldReceive('quoteIdentifier')->andReturn('"test_index"');
        $adapter->shouldReceive('getPlatform')->andReturn($this->platform);

        $sql = mock(ExtendedSql::class);
        $sql->shouldReceive('getAdapter')->andReturn($adapter);
        $sql->shouldReceive('getPrefix')->andReturn('smf_');

        $adapter->shouldReceive('query')
            ->with(Mockery::on(function ($sql) {
                return str_contains($sql, /** @lang text */ 'CREATE INDEX IF NOT EXISTS test_index ON smf_old_table (col1, col2)');
            }), Adapter::QUERY_MODE_EXECUTE);

        $upgrader = new class ($sql) extends AbstractTableUpgrader {
            protected string $tableName = 'old_table';

            public function upgrade(): void {}
        };

        $upgraderAccessor = new ReflectionAccessor($upgrader);
        $upgraderAccessor->callMethod('createIndexIfNotExists', ['test_index', 'col1, col2']);

        expect(true)->toBeTrue();
    });

    it('creates index if not exists for SQLite', function () {
        $adapter = mock(ExtendedAdapterInterface::class);
        $adapter->shouldReceive('getDriver')->andReturn(mock());
        $adapter->shouldReceive('getTitle')->andReturn('SQLite');

        $this->platform->shouldReceive('quoteIdentifier')->andReturn('"test_index"');
        $adapter->shouldReceive('getPlatform')->andReturn($this->platform);

        $sql = mock(ExtendedSql::class);
        $sql->shouldReceive('getAdapter')->andReturn($adapter);
        $sql->shouldReceive('getPrefix')->andReturn('smf_');

        $adapter->shouldReceive('query')
            ->with(Mockery::on(function ($sql) {
                return str_contains($sql, /** @lang text */ 'CREATE INDEX IF NOT EXISTS test_index ON smf_old_table (col1, col2)');
            }), Adapter::QUERY_MODE_EXECUTE);

        $upgrader = new class ($sql) extends AbstractTableUpgrader {
            protected string $tableName = 'old_table';

            public function upgrade(): void {}
        };

        $upgraderAccessor = new ReflectionAccessor($upgrader);
        $upgraderAccessor->callMethod('createIndexIfNotExists', ['test_index', 'col1, col2']);

        expect(true)->toBeTrue();
    });
});

<?php

declare(strict_types=1);

use Laminas\Db\Adapter\Platform\Mysql;
use Laminas\Db\Adapter\Platform\Sqlite;
use Laminas\Db\Extra\Sql\ExtendedTable;
use Laminas\Db\Sql\Ddl\Column\Integer;
use Laminas\Db\Sql\Ddl\Column\Varchar;

describe('ExtendedTable', function () {
    beforeEach(function () {
        $this->table = new ExtendedTable('test_table');
    });

    it('adds auto increment column and primary key', function () {
        $column = new Integer('id', false, null, ['auto_increment' => true]);

        $result = $this->table->addAutoIncrementColumn($column);

        expect($result)->toBeInstanceOf(ExtendedTable::class);
    });

    it('adds primary key', function () {
        $column = new Integer('id');

        $this->table->addColumn($column);
        $result = $this->table->addPrimaryKey('id');

        expect($result)->toBeInstanceOf(ExtendedTable::class);
    });

    it('adds unique column with index name', function () {
        $column = new Varchar('slug', 255);

        $result = $this->table->addUniqueColumn($column, 'unique_slug');

        expect($result)->toBeInstanceOf(ExtendedTable::class);
    });

    it('adds unique column without index name', function () {
        $column = new Varchar('alias', 100);

        $result = $this->table->addUniqueColumn($column);

        expect($result)->toBeInstanceOf(ExtendedTable::class);
    });

    it('adds unique key with name', function () {
        $column = new Varchar('title', 255);

        $this->table->addColumn($column);
        $result = $this->table->addUniqueKey('title', 'uk_title');

        expect($result)->toBeInstanceOf(ExtendedTable::class);
    });

    it('adds unique key without name', function () {
        $column = new Integer('category_id');

        $this->table->addColumn($column);
        $result = $this->table->addUniqueKey('category_id');

        expect($result)->toBeInstanceOf(ExtendedTable::class);
    });

    it('adds index', function () {
        $result = $this->table->addIndex('idx_name', ['first_name', 'last_name']);

        expect($result)->toBeInstanceOf(ExtendedTable::class);
    });

    it('getSqlString returns index statements for SQLite', function () {
        $this->table->addIndex('idx_email', ['email']);

        $platform = mock(Sqlite::class);
        $platform->shouldReceive('getName')->andReturn('SQLite');
        $platform->shouldReceive('quoteIdentifier')->andReturnUsing(function ($value) {
            return '"' . $value . '"';
        });

        $sql = $this->table->getSqlString($platform);
        $indexStatements = $this->table->getIndexSqlStatements($platform);

        expect($sql)->toContain('CREATE TABLE')
            ->and($indexStatements)->toHaveCount(1)
            ->and($indexStatements[0])->toContain('CREATE INDEX');
    });

    it('getSqlString returns parent SQL for MySQL', function () {
        $this->table->addIndex('idx_email', ['email']);

        $platform = mock(Mysql::class);
        $platform->shouldReceive('getName')->andReturn('MySQL');
        $platform->shouldReceive('quoteIdentifier')->andReturnUsing(function ($value) {
            return '`' . $value . '`';
        });
        $platform->shouldReceive('quoteIdentifierInFragment')->andReturnUsing(function ($value) {
            return '`' . $value . '`';
        });

        $sql = $this->table->getSqlString($platform);
        $indexStatements = $this->table->getIndexSqlStatements($platform);

        expect($sql)->toContain('CREATE TABLE')
            ->and($indexStatements)->toBeEmpty();
    });

    it('getIndexSqlStatements returns empty array for MySQL', function () {
        $this->table->addIndex('idx_email', ['email']);

        $platform = mock(Mysql::class);
        $platform->shouldReceive('getName')->andReturn('MySQL');

        $indexStatements = $this->table->getIndexSqlStatements($platform);

        expect($indexStatements)->toBeEmpty();
    });

    it('separates indexes from constraints for SQLite', function () {
        $this->table->addIndex('idx_email', ['email']);
        $this->table->addPrimaryKey('id');

        $platform = mock(Sqlite::class);
        $platform->shouldReceive('getName')->andReturn('SQLite');
        $platform->shouldReceive('quoteIdentifier')->andReturnUsing(function ($value) {
            return '"' . $value . '"';
        });
        $platform->shouldReceive('quoteIdentifierInFragment')->andReturnUsing(function ($value) {
            return '"' . $value . '"';
        });

        $sql = $this->table->getSqlString($platform);

        expect($sql)->toContain('CREATE TABLE')
            ->and($sql)->not->toContain('idx_email');
    });

    it('getIndexSqlStatements populates separateIndexes from constraints', function () {
        $this->table->addIndex('idx_email', ['email']);
        $this->table->addPrimaryKey('id');

        $platform = mock(Sqlite::class);
        $platform->shouldReceive('getName')->andReturn('SQLite');
        $platform->shouldReceive('quoteIdentifier')->andReturnUsing(function ($value) {
            return '"' . $value . '"';
        });

        $indexStatements = $this->table->getIndexSqlStatements($platform);

        expect($indexStatements)->toHaveCount(1)
            ->and($indexStatements[0])->toContain('idx_email');
    });
});

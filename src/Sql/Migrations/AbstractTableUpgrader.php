<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Sql\Migrations;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\Driver\StatementInterface;
use Laminas\Db\Extra\Sql\Columns\MediumText;
use Laminas\Db\Extra\Sql\ExtendedSqlInterface;
use Laminas\Db\ResultSet\ResultSetInterface;
use Laminas\Db\Sql\Ddl\AlterTable;
use Laminas\Db\Sql\Ddl\Column\Column;
use Laminas\Db\Sql\Ddl\Column\ColumnInterface;
use Laminas\Db\Sql\Ddl\Column\Integer;
use Laminas\Db\Sql\Ddl\Column\Varchar;
use Laminas\Db\Sql\SqlInterface;

abstract class AbstractTableUpgrader implements TableUpgraderInterface
{
    protected string $tableName;

    public function __construct(protected ExtendedSqlInterface $sql) {}

    public function updateTable(): void
    {
        if (! $this->sql->tableExists($this->tableName)) {
            return;
        }

        $this->upgrade();
    }

    protected function defineColumn(string $columnName, array $params = []): ColumnInterface
    {
        $type     = $params['type'] ?? 'varchar';
        $size     = $params['size'] ?? 255;
        $nullable = $params['nullable'] ?? false;
        $default  = $params['default'] ?? null;

        $column = match ($type) {
            'mediumtext' => new MediumText($columnName, nullable: $nullable, default: $default),
            'varchar'    => new Varchar($columnName, $size, $nullable, $default),
            'int'        => new Integer($columnName, $nullable, $default),
            default      => new Column($columnName, $nullable, $default, options: ['type' => $type]),
        };

        if ($type === 'int' && $size) {
            $column->setOption('length', $size);
        }

        return $column;
    }

    protected function getFullTableName(?string $name = null): string
    {
        return $this->sql->getPrefix() . ($name ?? $this->tableName);
    }

    protected function executeSql(SqlInterface $builder): void
    {
        $sqlString = $this->sql->buildSqlString($builder);

        $platform = $this->sql->getAdapter()->getTitle();
        if ($platform === 'PostgreSQL') {
            $sqlString = preg_replace(
                '/\b(INTEGER|SMALLINT|BIGINT|INT)\(\d+\)/i',
                '$1',
                $sqlString
            );
        }

        $this->query($sqlString);
    }

    protected function alterColumn(
        string $action,
        string $columnName,
        array $params = [],
        ?string $newName = null
    ): void {
        $exists = $this->sql->columnExists($this->tableName, $columnName);

        if (($action === 'add' && $exists) || ($action === 'drop' && ! $exists)) {
            return;
        }

        if ($action === 'change' && ! $exists) {
            return;
        }

        $alter = new AlterTable($this->getFullTableName());

        if ($action === 'add') {
            $alter->addColumn($this->defineColumn($columnName, $params));
        } elseif ($action === 'change') {
            if ($columnName !== $newName) {
                $this->renameColumn($columnName, $newName);
            }

            if ($params) {
                $alter->changeColumn($newName, $this->defineColumn($newName, $params));
            } else {
                return;
            }
        } elseif ($action === 'drop') {
            $alter->dropColumn($columnName);
        }

        $this->executeSql($alter);
    }

    protected function renameColumn(string $oldName, string $newName): void
    {
        $sql = sprintf(
            /** @lang text */
            'ALTER TABLE %s RENAME COLUMN %s TO %s',
            $this->getFullTableName(),
            $this->sql->getAdapter()->getPlatform()->quoteIdentifier($oldName),
            $this->sql->getAdapter()->getPlatform()->quoteIdentifier($newName)
        );

        $this->query($sql);
    }

    protected function addColumn(string $columnName, array $params = []): void
    {
        $this->alterColumn('add', $columnName, $params);
    }

    protected function changeColumn(string $oldName, string $newName, array $params = []): void
    {
        $this->alterColumn('change', $oldName, $params, $newName);
    }

    protected function dropColumn(string $column): void
    {
        if ($this->isSqlite()) {
            $this->dropColumnSqlite($column);
        }

        $this->alterColumn('drop', $column);
    }

    protected function addIndex(string $indexName, array $columns): void
    {
        $columnList = implode(', ', $columns);

        $this->createIndexIfNotExists($indexName, $columnList);
    }

    protected function addPrefixIndex(string $indexName, string $column, int $length): void
    {
        $platformName = strtolower($this->sql->getAdapter()->getTitle());

        $expr = match ($platformName) {
            'postgresql' => "substring($column FROM 1 FOR $length)",
            'sqlite'     => "substr($column, 1, $length)",
            default      => "$column($length)",
        };

        $this->createIndexIfNotExists($indexName, $expr);
    }

    protected function renameTable(string $new): void
    {
        $oldTable = $this->getFullTableName();
        $newTable = $this->getFullTableName($new);

        $this->query(/** @lang text */ "ALTER TABLE $oldTable RENAME TO $newTable");
    }

    protected function isSqlite(): bool
    {
        return strtolower($this->sql->getAdapter()->getTitle()) === 'sqlite';
    }

    protected function dropColumnSqlite(string $columnName): void
    {
        $fullTableName = $this->getFullTableName();
        $columns = $this->query("PRAGMA table_info($fullTableName)");

        $columnNames = $columnDefs = [];
        foreach ($columns as $column) {
            if ($column['name'] === $columnName) {
                continue;
            }

            $columnNames[] = $column['name'];

            $def = $column['name'] . ' ' . $column['type'];
            if ($column['notnull']) {
                $def .= ' NOT NULL';
            }

            if ($column['dflt_value'] !== null) {
                $def .= ' DEFAULT ' . $this->sql->getAdapter()->getPlatform()->quoteValue($column['dflt_value']);
            }

            if ($column['pk']) {
                $def .= ' PRIMARY KEY';
            }

            $columnDefs[] = $def;
        }

        $tempTable = $fullTableName . '_temp_' . time();
        $columns   = implode(', ', $columnDefs);
        $this->query(/** @lang text */ "CREATE TABLE $tempTable ($columns)");

        $selectColumns = implode(', ', $columnNames);
        $this->query(/** @lang text */ "INSERT INTO $tempTable ($selectColumns) SELECT $selectColumns FROM $fullTableName");

        $this->query(/** @lang text */ "DROP TABLE $fullTableName");
        $this->query(/** @lang text */ "ALTER TABLE $tempTable RENAME TO $this->tableName");
    }

    protected function query(string $sql): ResultSetInterface|StatementInterface|null
    {
        return $this->sql->getAdapter()->query($sql, Adapter::QUERY_MODE_EXECUTE);
    }

    private function createIndexIfNotExists(string $indexName, string $columnsExpr): void
    {
        $fullTableName = $this->getFullTableName();
        $platformName  = strtolower($this->sql->getAdapter()->getTitle());

        $quotedIndexName = $indexName;
        if ($platformName === 'mysql') {
            $quotedIndexName = $this->sql->getAdapter()->getPlatform()->quoteIdentifier($indexName);
        }

        if ($platformName === 'mysql') {
            $result = $this->query("SHOW INDEX FROM $fullTableName WHERE Key_name = '$indexName'");

            if (empty($result->toArray())) {
                $this->query(/** @lang text */ "CREATE INDEX $quotedIndexName ON $fullTableName ($columnsExpr)");
            }
        } else {
            $this->query(/** @lang text */ "CREATE INDEX IF NOT EXISTS $quotedIndexName ON $fullTableName ($columnsExpr)");
        }
    }
}

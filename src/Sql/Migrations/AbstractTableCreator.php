<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Sql\Migrations;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Extra\Sql\ExtendedSqlInterface;
use Laminas\Db\Extra\Sql\ExtendedTable;
use Laminas\Db\Sql\Ddl\DropTable;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\SqlInterface;

abstract class AbstractTableCreator implements TableCreatorInterface
{
    protected string $tableName;

    private ?ExtendedTable $table = null;

    public function __construct(protected ExtendedSqlInterface $sql) {}

    public function createTable(): void
    {
        if ($this->sql->tableExists($this->tableName)) {
            return;
        }

        $this->table = new ExtendedTable($this->getFullTableName());
        $this->defineColumns($this->table);
        $this->executeSql($this->table);

        $adapter = $this->sql->getAdapter();
        foreach ($this->table->getIndexSqlStatements($adapter->getPlatform()) as $indexSql) {
            $adapter->query($indexSql, Adapter::QUERY_MODE_EXECUTE);
        }
    }

    public function getSql(): string
    {
        if ($this->table === null) {
            $this->table = new ExtendedTable($this->getFullTableName());
            $this->defineColumns($this->table);
        }

        return $this->sql->buildSqlString($this->table);
    }

    public function insertDefaultData(): void
    {
        $data = $this->getDefaultData();

        if (empty($data)) {
            return;
        }

        $this->insertDefaultIfNotExists(...$data);
    }

    public function dropTable(): void
    {
        if (! $this->sql->tableExists($this->tableName)) {
            return;
        }

        $dropTable = new DropTable($this->getFullTableName());
        $this->executeSql($dropTable);
    }

    abstract protected function defineColumns(ExtendedTable $table): void;

    abstract protected function getDefaultData(): array;

    protected function getFullTableName(): string
    {
        return $this->sql->getPrefix() . $this->tableName;
    }

    protected function executeSql(SqlInterface $builder): void
    {
        $sqlString = $this->sql->buildSqlString($builder);

        $this->sql->getAdapter()->query($sqlString, Adapter::QUERY_MODE_EXECUTE);
    }

    protected function insertDefaultIfNotExists(array $where, array $columns, array $values): void
    {
        $select = $this->sql->select($this->tableName);
        $select->columns(['count' => new Expression('COUNT(*)')], false);
        $select->where($where);

        $row = $this->sql->execute($select)->current();

        if ($row['count'] == 0) {
            $insert = $this->sql->insert($this->tableName);

            if (is_array($values[0])) {
                foreach ($values as $value) {
                    $insert->columns($columns)->values($value);
                    $this->sql->execute($insert);
                }

                return;
            }

            $insert->columns($columns)->values($values);
            $this->sql->execute($insert);
        }
    }
}

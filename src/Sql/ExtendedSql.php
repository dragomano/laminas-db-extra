<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Sql;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Extra\Adapter\ExtendedAdapterInterface;
use Laminas\Db\Extra\Result\ExtendedResult;
use Laminas\Db\Extra\Result\ExtendedResultInterface;
use Laminas\Db\Extra\Sql\Operations\ExtendedDelete;
use Laminas\Db\Extra\Sql\Operations\ExtendedInsert;
use Laminas\Db\Extra\Sql\Operations\ExtendedReplace;
use Laminas\Db\Extra\Sql\Operations\ExtendedSelect;
use Laminas\Db\Extra\Sql\Operations\ExtendedUpdate;
use Laminas\Db\Metadata\MetadataInterface;
use Laminas\Db\Metadata\Source\Factory;
use Laminas\Db\Sql\PreparableSqlInterface;
use Laminas\Db\Sql\Sql;
use Throwable;

class ExtendedSql extends Sql implements ExtendedSqlInterface
{
    protected $adapter;

    private readonly string $prefix;

    public function __construct(
        ExtendedAdapterInterface $adapter,
        private readonly ?MetadataInterface $metadata = null
    ) {
        parent::__construct($adapter);

        $this->prefix = $adapter->getPrefix();
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function tableExists(string $table): bool
    {
        [$platform, $tableName] = $this->resolvePlatformAndTableName($table);

        try {
            if ($platform === 'sqlite') {
                $result = $this->adapter
                    ->query(
                        /** @lang text */
                        'SELECT 1 FROM sqlite_master WHERE type = ? AND name = ?',
                        ['table', $tableName]
                    );

                return (bool) $result->current();
            }

            $metadata = $this->getMetadata();

            return in_array($tableName, $metadata->getTableNames(), true);
        } catch (Throwable) {
            return false;
        }
    }

    public function columnExists(string $table, string $column): bool
    {
        [$platform, $tableName] = $this->resolvePlatformAndTableName($table);

        if ($platform === 'sqlite') {
            try {
                $statement = $this->adapter->query(
                    "PRAGMA table_info($tableName)",
                    Adapter::QUERY_MODE_EXECUTE
                );
                $result = $statement->execute();

                foreach ($result as $row) {
                    if ($row['name'] === $column) {
                        return true;
                    }
                }

                return false;
            } catch (Throwable) {
                return false;
            }
        }

        try {
            $metadata = $this->getMetadata();

            return in_array($column, $metadata->getColumnNames($tableName), true);
        } catch (Throwable) {
            return false;
        }
    }

    public function getAdapter(): ExtendedAdapterInterface
    {
        return $this->adapter;
    }

    public function getTransaction(): TransactionInterface
    {
        return new Transaction($this->adapter);
    }

    public function select($table = null): ExtendedSelect
    {
        return new ExtendedSelect($table, $this->prefix);
    }

    public function insert($table = null, array|string|null $returning = null): ExtendedInsert
    {
        return new ExtendedInsert($table, $this->prefix, $returning);
    }

    public function update($table = null): ExtendedUpdate
    {
        return new ExtendedUpdate($table, $this->prefix);
    }

    public function delete($table = null): ExtendedDelete
    {
        return new ExtendedDelete($table, $this->prefix);
    }

    public function replace($table = null, array|string|null $returning = null): ExtendedReplace
    {
        return new ExtendedReplace($table, $this->prefix, $returning);
    }

    public function execute(PreparableSqlInterface $sqlObject): ?ExtendedResultInterface
    {
        try {
            if ($sqlObject instanceof ExtendedReplace) {
                if ($sqlObject->isBatch()) {
                    $result = $sqlObject->executeBatchReplace($this->adapter);
                } else {
                    $result = $sqlObject->executeReplace($this->adapter);
                }
            } elseif ($sqlObject instanceof ExtendedInsert) {
                if ($sqlObject->isBatch()) {
                    $result = $sqlObject->executeBatch($this->adapter);
                } else {
                    $result = $sqlObject->executeInsert($this->adapter);
                }
            } else {
                $result = $this->prepareStatementForSqlObject($sqlObject)->execute();
            }

            return new ExtendedResult($result, $this->adapter);
        } catch (Throwable $e) {
            $this->handleExecutionError($e);
        }

        return null;
    }

    protected function getMetadata(): MetadataInterface
    {
        if ($this->metadata !== null) {
            return $this->metadata;
        }

        $sourceFactoryClass = Factory::class;

        return $sourceFactoryClass::createSourceFromAdapter($this->adapter);
    }

    protected function resolvePlatformAndTableName(string $table): array
    {
        $platform  = strtolower((string) $this->adapter->getTitle());
        $tableName = $this->prefix . $table;

        return [$platform, $tableName];
    }

    protected function handleExecutionError(Throwable $e): void {}
}

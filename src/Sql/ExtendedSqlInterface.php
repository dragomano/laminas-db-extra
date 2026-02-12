<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Sql;

use Laminas\Db\Extra\Adapter\ExtendedAdapterInterface;
use Laminas\Db\Extra\Result\ExtendedResultInterface;
use Laminas\Db\Extra\Sql\Operations\ExtendedDelete;
use Laminas\Db\Extra\Sql\Operations\ExtendedInsert;
use Laminas\Db\Extra\Sql\Operations\ExtendedReplace;
use Laminas\Db\Extra\Sql\Operations\ExtendedSelect;
use Laminas\Db\Extra\Sql\Operations\ExtendedUpdate;
use Laminas\Db\Sql\PreparableSqlInterface;

interface ExtendedSqlInterface
{
    public function getPrefix(): string;

    public function tableExists(string $table): bool;

    public function columnExists(string $table, string $column): bool;

    public function getAdapter(): ExtendedAdapterInterface;

    public function getTransaction(): TransactionInterface;

    public function select($table = null): ExtendedSelect;

    public function insert($table = null): ExtendedInsert;

    public function update($table = null): ExtendedUpdate;

    public function delete($table = null): ExtendedDelete;

    public function replace($table = null): ExtendedReplace;

    public function execute(PreparableSqlInterface $sqlObject): ?ExtendedResultInterface;
}

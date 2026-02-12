<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Sql\Migrations;

interface TableCreatorInterface
{
    public function createTable(): void;

    public function insertDefaultData(): void;

    public function dropTable(): void;
}

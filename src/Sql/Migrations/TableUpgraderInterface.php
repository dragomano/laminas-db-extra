<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Sql\Migrations;

interface TableUpgraderInterface
{
    public function upgrade(): void;
}

<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Sql\Migrations;

interface InstallerInterface
{
    public function install(): bool;

    public function uninstall(): bool;

    public function upgrade(): bool;
}

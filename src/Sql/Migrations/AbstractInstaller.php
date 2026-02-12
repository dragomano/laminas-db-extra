<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Sql\Migrations;

use Laminas\Db\Extra\Adapter\AdapterFactory;
use Laminas\Db\Extra\Adapter\DbPlatform;
use Laminas\Db\Extra\Sql\ExtendedSql;
use Laminas\Db\Extra\Sql\ExtendedSqlInterface;

abstract class AbstractInstaller implements InstallerInterface
{
    public function __construct(protected ?ExtendedSqlInterface $sql = null)
    {
        $this->sql ??= new ExtendedSql(AdapterFactory::create());

        DbPlatform::set($this->sql->getAdapter()->getPlatform());
    }

    public function install(): bool
    {
        $this->processTables('install');

        return true;
    }

    public function uninstall(): bool
    {
        $this->processTables('uninstall');

        return true;
    }

    public function upgrade(): bool
    {
        $this->processUpgradeTasks();

        return true;
    }

    abstract protected function getCreators(): array;

    abstract protected function getUpgraders(): array;

    protected function processTables(string $mode): void
    {
        $creators = $this->getCreators();

        foreach ($creators as $creatorClass) {
            $creator = new $creatorClass($this->sql);
            if (! $creator instanceof TableCreatorInterface) {
                continue;
            }

            if ($mode === 'install') {
                $creator->createTable();
                $creator->insertDefaultData();
            } elseif ($mode === 'uninstall') {
                $creator->dropTable();
            }
        }
    }

    protected function processUpgradeTasks(): void
    {
        $upgraders = $this->getUpgraders();

        foreach ($upgraders as $upgraderClass) {
            $upgrader = new $upgraderClass($this->sql);
            if (! $upgrader instanceof TableUpgraderInterface) {
                continue;
            }

            $upgrader->updateTable();
        }
    }
}

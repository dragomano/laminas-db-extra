<?php

declare(strict_types=1);

use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Extra\Adapter\DbPlatform;
use Laminas\Db\Extra\Adapter\ExtendedAdapterInterface;
use Laminas\Db\Extra\Sql\ExtendedSqlInterface;
use Laminas\Db\Extra\Sql\Migrations\AbstractInstaller;
use Laminas\Db\Extra\Sql\Migrations\InstallerInterface;
use Laminas\Db\Extra\Sql\Migrations\TableCreatorInterface;
use Laminas\Db\Extra\Sql\Migrations\TableUpgraderInterface;
use Tests\ReflectionAccessor;

describe('AbstractInstaller', function () {
    beforeEach(function () {
        $this->platform = mock(PlatformInterface::class);
        $this->platform->shouldReceive('getName')->andReturn('MySQL');

        $this->adapter = mock(ExtendedAdapterInterface::class);
        $this->adapter->shouldReceive('getPlatform')->andReturn($this->platform);

        $this->sql = mock(ExtendedSqlInterface::class);
        $this->sql->shouldReceive('getAdapter')->andReturn($this->adapter);

        $this->createTestInstaller = function (array $creators = [], array $upgraders = []) {
            return new class ($this->sql, $creators, $upgraders) extends AbstractInstaller {
                protected array $testCreators;
                protected array $testUpgraders;

                public function __construct($sql, array $creators, array $upgraders)
                {
                    $this->testCreators = $creators;
                    $this->testUpgraders = $upgraders;
                    parent::__construct($sql);
                }

                protected function getCreators(): array
                {
                    return $this->testCreators;
                }

                protected function getUpgraders(): array
                {
                    return $this->testUpgraders;
                }
            };
        };

        $this->createMockCreator = function () {
            return new class ($this->sql) implements TableCreatorInterface {
                public static bool $createTableCalled = false;
                public static bool $insertDefaultDataCalled = false;
                public static bool $dropTableCalled = false;

                public function __construct($sql) {}

                public function createTable(): void
                {
                    self::$createTableCalled = true;
                }

                public function insertDefaultData(): void
                {
                    self::$insertDefaultDataCalled = true;
                }

                public function dropTable(): void
                {
                    self::$dropTableCalled = true;
                }
            };
        };

        $this->createMockUpgrader = function () {
            return new class ($this->sql) implements TableUpgraderInterface {
                public static bool $updateTableCalled = false;

                public function __construct($sql) {}

                public function upgrade(): void
                {
                    self::$updateTableCalled = true;
                }

                public function updateTable(): void
                {
                    self::$updateTableCalled = true;
                }
            };
        };
    });

    afterEach(function () {
        DbPlatform::set(null);
    });

    it('implements InstallerInterface', function () {
        $installer = ($this->createTestInstaller)();

        expect($installer)->toBeInstanceOf(InstallerInterface::class);
    });

    it('constructs with provided sql', function () {
        $installer = ($this->createTestInstaller)();

        $accessor = new ReflectionAccessor($installer);
        $sqlProperty = $accessor->getProperty('sql');

        expect($sqlProperty)->toBe($this->sql);
    });

    it('sets db platform on construction', function () {
        ($this->createTestInstaller)();

        expect(DbPlatform::get())->toBe($this->platform);
    });

    it('install returns true', function () {
        $installer = ($this->createTestInstaller)();

        $result = $installer->install();

        expect($result)->toBeTrue();
    });

    it('uninstall returns true', function () {
        $installer = ($this->createTestInstaller)();

        $result = $installer->uninstall();

        expect($result)->toBeTrue();
    });

    it('upgrade returns true', function () {
        $installer = ($this->createTestInstaller)();

        $result = $installer->upgrade();

        expect($result)->toBeTrue();
    });

    it('processTables calls createTable and insertDefaultData on install', function () {
        $mockCreator = ($this->createMockCreator)();
        $creatorClass = get_class($mockCreator);

        $accessor = new ReflectionAccessor($creatorClass);
        $accessor->setProperty('createTableCalled', false);
        $accessor->setProperty('insertDefaultDataCalled', false);

        $installer = ($this->createTestInstaller)([$creatorClass], []);

        $installer->install();

        expect($accessor->getProperty('createTableCalled'))->toBeTrue()
            ->and($accessor->getProperty('insertDefaultDataCalled'))->toBeTrue();
    });

    it('processTables calls dropTable on uninstall', function () {
        $mockCreator = ($this->createMockCreator)();
        $creatorClass = get_class($mockCreator);

        $accessor = new ReflectionAccessor($creatorClass);
        $accessor->setProperty('dropTableCalled', false);

        $installer = ($this->createTestInstaller)([$creatorClass], []);

        $installer->uninstall();

        expect($accessor->getProperty('dropTableCalled'))->toBeTrue();
    });

    it('processTables skips non TableCreatorInterface instances', function () {
        $installer = ($this->createTestInstaller)(['stdClass'], []);

        $result = $installer->install();

        expect($result)->toBeTrue();
    });

    it('processUpgradeTasks calls updateTable on upgraders', function () {
        $mockUpgrader = ($this->createMockUpgrader)();
        $upgraderClass = get_class($mockUpgrader);

        $accessor = new ReflectionAccessor($upgraderClass);
        $accessor->setProperty('updateTableCalled', false);

        $installer = ($this->createTestInstaller)([], [$upgraderClass]);

        $installer->upgrade();

        expect($accessor->getProperty('updateTableCalled'))->toBeTrue();
    });

    it('processUpgradeTasks skips non TableUpgraderInterface instances', function () {
        $installer = ($this->createTestInstaller)([], ['stdClass']);

        $result = $installer->upgrade();

        expect($result)->toBeTrue();
    });
});

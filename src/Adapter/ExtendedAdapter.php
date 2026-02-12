<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Adapter;

use Laminas\Db\Adapter\Adapter;

class ExtendedAdapter extends Adapter implements ExtendedAdapterInterface
{
    public function __construct(protected array $config)
    {
        parent::__construct($this->config);
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getPrefix(): string
    {
        return $this->config['prefix'] ?? '';
    }

    public function getVersion(): string
    {
        $query = $this->getTitle() === 'SQLite' ? 'SELECT sqlite_version() AS version' : 'SELECT VERSION() AS version';

        $result = $this->query($query, self::QUERY_MODE_EXECUTE);

        return $result->current()['version'];
    }

    public function getTitle(): string
    {
        return $this->getPlatform()->getName();
    }
}

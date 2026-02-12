<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Adapter;

use Laminas\Db\Adapter\AdapterInterface;

interface ExtendedAdapterInterface extends AdapterInterface
{
    public function getConfig(): array;

    public function getPrefix(): string;

    public function getVersion(): string;

    public function getTitle(): string;
}

<?php

declare(strict_types=1);

namespace Laminas\Db\Extra\Adapter;

use Laminas\Db\Adapter\Platform\PlatformInterface;

class DbPlatform
{
    private static ?PlatformInterface $platform = null;

    public static function set(?PlatformInterface $platform): void
    {
        self::$platform = $platform;
    }

    public static function get(): ?PlatformInterface
    {
        return self::$platform;
    }
}

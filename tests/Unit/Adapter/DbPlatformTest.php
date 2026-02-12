<?php

declare(strict_types=1);

use Laminas\Db\Adapter\Platform\Mysql;
use Laminas\Db\Adapter\Platform\Postgresql;
use Laminas\Db\Adapter\Platform\Sqlite;
use Laminas\Db\Extra\Adapter\DbPlatform;

describe('DbPlatform', function () {
    beforeEach(function () {
        DbPlatform::set(null);
    });

    afterEach(function () {
        DbPlatform::set(null);
    });

    it('sets and gets platform', function () {
        $platform = new Mysql([]);

        DbPlatform::set($platform);

        expect(DbPlatform::get())->toBe($platform);
    });

    it('returns null when platform is not set', function () {
        expect(DbPlatform::get())->toBeNull();
    });

    it('allows setting null platform', function () {
        $platform = new Mysql([]);
        DbPlatform::set($platform);
        DbPlatform::set(null);

        expect(DbPlatform::get())->toBeNull();
    });

    it('can be used with MySQL platform', function () {
        $platform = new Mysql([]);

        DbPlatform::set($platform);

        expect(DbPlatform::get())->toBeInstanceOf(Mysql::class);
    });

    it('can be used with PostgreSQL platform', function () {
        $platform = new Postgresql([]);

        DbPlatform::set($platform);

        expect(DbPlatform::get())->toBeInstanceOf(Postgresql::class);
    });

    it('can be used with SQLite platform', function () {
        $platform = new Sqlite([]);

        DbPlatform::set($platform);

        expect(DbPlatform::get())->toBeInstanceOf(Sqlite::class);
    });

    it('returns same platform instance that was set', function () {
        $platform = new Mysql([]);

        DbPlatform::set($platform);
        $retrieved = DbPlatform::get();

        expect($retrieved)->toBe($platform);
    });
});

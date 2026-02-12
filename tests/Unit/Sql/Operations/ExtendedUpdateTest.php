<?php

declare(strict_types=1);

use Laminas\Db\Extra\Sql\Operations\ExtendedUpdate;
use Tests\ReflectionAccessor;

describe('ExtendedUpdate', function () {
    it('constructs with prefix', function () {
        $update = new ExtendedUpdate('test', 'prefix_');

        expect($update)->toBeInstanceOf(ExtendedUpdate::class);
    });

    it('adds prefix to string table in table', function () {
        $update = new ExtendedUpdate('test', 'prefix_');

        $result = $update->table('users');

        expect($result)->toBeInstanceOf(ExtendedUpdate::class);

        $reflection = new ReflectionAccessor($update);
        $tableProperty = $reflection->getProperty('table');

        expect($tableProperty)->toBe('prefix_users');
    });

    it('does not add prefix when prefix is empty in table', function () {
        $update = new ExtendedUpdate('test', '');

        $update->table('users');

        $reflection = new ReflectionAccessor($update);
        $tableProperty = $reflection->getProperty('table');

        expect($tableProperty)->toBe('users');
    });
});

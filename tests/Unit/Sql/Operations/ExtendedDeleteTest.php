<?php

declare(strict_types=1);

use Laminas\Db\Extra\Sql\Operations\ExtendedDelete;
use Tests\ReflectionAccessor;

describe('ExtendedDelete', function () {
    it('constructs with prefix', function () {
        $delete = new ExtendedDelete('test', 'prefix_');

        expect($delete)->toBeInstanceOf(ExtendedDelete::class);
    });

    it('adds prefix to string table in from', function () {
        $delete = new ExtendedDelete('test', 'prefix_');

        $result = $delete->from('users');

        expect($result)->toBeInstanceOf(ExtendedDelete::class);

        $reflection = new ReflectionAccessor($delete);
        $tableProperty = $reflection->getProperty('table');

        expect($tableProperty)->toBe('prefix_users');
    });

    it('does not add prefix when prefix is empty', function () {
        $delete = new ExtendedDelete('test', '');

        $delete->from('users');

        $reflection = new ReflectionAccessor($delete);
        $tableProperty = $reflection->getProperty('table');

        expect($tableProperty)->toBe('users');
    });
});

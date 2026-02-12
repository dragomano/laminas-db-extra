<?php

declare(strict_types=1);

use Laminas\Db\Extra\Sql\Operations\ExtendedSelect;
use Tests\ReflectionAccessor;

describe('ExtendedSelect', function () {
    it('constructs with prefix', function () {
        $select = new ExtendedSelect(prefix: 'prefix_');

        expect($select)->toBeInstanceOf(ExtendedSelect::class);
    });

    it('adds prefix to string table in from', function () {
        $select = new ExtendedSelect(null, 'prefix_');

        $result = $select->from('users');

        expect($result)->toBeInstanceOf(ExtendedSelect::class);

        $reflection = new ReflectionAccessor($select);
        $tableProperty = $reflection->getProperty('table');

        expect($tableProperty)->toBe('prefix_users');
    });

    it('does not add prefix when prefix is empty', function () {
        $select = new ExtendedSelect(null, '');

        $select->from('users');

        $reflection = new ReflectionAccessor($select);
        $tableProperty = $reflection->getProperty('table');

        expect($tableProperty)->toBe('users');
    });

    it('adds prefix to string tables in array from', function () {
        $select = new ExtendedSelect(null, 'prefix_');

        $select->from(['u' => 'users', 'p' => 'posts']);

        $reflection = new ReflectionAccessor($select);
        $tableProperty = $reflection->getProperty('table');

        expect($tableProperty)->toBe(['u' => 'prefix_users', 'p' => 'prefix_posts']);
    });

    it('leaves non-string tables unchanged in array from', function () {
        $select = new ExtendedSelect(null, 'prefix_');

        $obj = new stdClass();
        $select->from(['u' => 'users', 'o' => $obj]);

        $reflection = new ReflectionAccessor($select);
        $tableProperty = $reflection->getProperty('table');

        expect($tableProperty)->toBe(['u' => 'prefix_users', 'o' => $obj]);
    });

    it('throws exception for invalid table types', function ($invalidTable) {
        expect(fn() => new ExtendedSelect($invalidTable, 'prefix_'))
            ->toThrow(InvalidArgumentException::class);
    })->with([
        [123],
        [new stdClass()],
        [true],
    ]);

    it('adds prefix to string table in join', function () {
        $select = new ExtendedSelect(null, 'prefix_');

        $result = $select->join('users', 'posts.user_id = users.id');

        expect($result)->toBeInstanceOf(ExtendedSelect::class);
    });

    it('adds prefix to aliased table in join', function () {
        $select = new ExtendedSelect(null, 'prefix_');

        $result = $select->join(['u' => 'users'], 'posts.user_id = u.id');

        expect($result)->toBeInstanceOf(ExtendedSelect::class);
    });

    it('does not add prefix when prefix is empty in join', function () {
        $select = new ExtendedSelect(null, '');

        $result = $select->join('users', 'posts.user_id = users.id');

        expect($result)->toBeInstanceOf(ExtendedSelect::class);
    });
});

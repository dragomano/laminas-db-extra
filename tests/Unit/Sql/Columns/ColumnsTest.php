<?php

declare(strict_types=1);

use Laminas\Db\Adapter\Platform\Mysql;
use Laminas\Db\Adapter\Platform\Postgresql;
use Laminas\Db\Adapter\Platform\Sqlite;
use Laminas\Db\Extra\Adapter\DbPlatform;
use Laminas\Db\Extra\Sql\Columns\AutoIncrementInteger;
use Laminas\Db\Extra\Sql\Columns\MediumInteger;
use Laminas\Db\Extra\Sql\Columns\MediumText;
use Laminas\Db\Extra\Sql\Columns\SmallInteger;
use Laminas\Db\Extra\Sql\Columns\TinyInteger;
use Laminas\Db\Extra\Sql\Columns\UnsignedInteger;
use Tests\ReflectionAccessor;

describe('Columns', function () {
    beforeEach(function () {
        DbPlatform::set(null);
    });

    afterEach(function () {
        DbPlatform::set(null);
    });

    describe('UnsignedInteger', function () {
        it('constructs with default values', function () {
            $column = new UnsignedInteger('test_id');

            expect($column->getName())->toBe('test_id');
        });

        it('sets unsigned option', function () {
            $column = new UnsignedInteger('test_id');
            $accessor = new ReflectionAccessor($column);

            $options = $accessor->getProperty('options');

            expect($options['unsigned'])->toBeTrue();
        });

        it('constructs with nullable', function () {
            $column = new UnsignedInteger('test_id', true);

            expect($column->isNullable())->toBeTrue();
        });

        it('constructs with default value', function () {
            $column = new UnsignedInteger('test_id', false, 100);

            expect($column->getDefault())->toBe(100);
        });

        it('merges custom options', function () {
            $column = new UnsignedInteger('test_id', false, 0, ['custom' => 'value']);
            $accessor = new ReflectionAccessor($column);

            $options = $accessor->getProperty('options');

            expect($options['unsigned'])->toBeTrue()
                ->and($options['custom'])->toBe('value');
        });
    });

    describe('SmallInteger', function () {
        it('constructs with default values', function () {
            $column = new SmallInteger('small_id');

            expect($column->getName())->toBe('small_id');
        });

        it('has SMALLINT type', function () {
            $column = new SmallInteger('small_id');
            $accessor = new ReflectionAccessor($column);

            expect($accessor->getProperty('type'))->toBe('SMALLINT');
        });

        it('inherits unsigned option from parent', function () {
            $column = new SmallInteger('small_id');
            $accessor = new ReflectionAccessor($column);

            $options = $accessor->getProperty('options');

            expect($options['unsigned'])->toBeTrue();
        });
    });

    describe('MediumInteger', function () {
        it('constructs with MEDIUMINT type for MySQL', function () {
            DbPlatform::set(new Mysql());

            $column = new MediumInteger('medium_id');
            $accessor = new ReflectionAccessor($column);

            expect($accessor->getProperty('type'))->toBe('MEDIUMINT');
        });

        it('constructs with MEDIUMINT type for SQLite', function () {
            DbPlatform::set(new Sqlite());

            $column = new MediumInteger('medium_id');
            $accessor = new ReflectionAccessor($column);

            expect($accessor->getProperty('type'))->toBe('MEDIUMINT');
        });

        it('constructs with INTEGER type for PostgreSQL', function () {
            DbPlatform::set(new Postgresql());

            $column = new MediumInteger('medium_id');
            $accessor = new ReflectionAccessor($column);

            expect($accessor->getProperty('type'))->toBe('INTEGER');
        });

        it('constructs with MEDIUMINT type when no platform set', function () {
            $column = new MediumInteger('medium_id');
            $accessor = new ReflectionAccessor($column);

            expect($accessor->getProperty('type'))->toBe('MEDIUMINT');
        });

        it('inherits unsigned option from parent', function () {
            DbPlatform::set(new Mysql());

            $column = new MediumInteger('medium_id');
            $accessor = new ReflectionAccessor($column);

            $options = $accessor->getProperty('options');

            expect($options['unsigned'])->toBeTrue();
        });
    });

    describe('TinyInteger', function () {
        it('constructs with TINYINT type for MySQL', function () {
            DbPlatform::set(new Mysql());

            $column = new TinyInteger('tiny_id');
            $accessor = new ReflectionAccessor($column);

            expect($accessor->getProperty('type'))->toBe('TINYINT');
        });

        it('constructs with TINYINT type for SQLite', function () {
            DbPlatform::set(new Sqlite());

            $column = new TinyInteger('tiny_id');
            $accessor = new ReflectionAccessor($column);

            expect($accessor->getProperty('type'))->toBe('TINYINT');
        });

        it('constructs with SMALLINT type for PostgreSQL', function () {
            DbPlatform::set(new Postgresql());

            $column = new TinyInteger('tiny_id');
            $accessor = new ReflectionAccessor($column);

            expect($accessor->getProperty('type'))->toBe('SMALLINT');
        });

        it('constructs with TINYINT type when no platform set', function () {
            $column = new TinyInteger('tiny_id');
            $accessor = new ReflectionAccessor($column);

            expect($accessor->getProperty('type'))->toBe('TINYINT');
        });

        it('inherits unsigned option from parent', function () {
            DbPlatform::set(new Mysql());

            $column = new TinyInteger('tiny_id');
            $accessor = new ReflectionAccessor($column);

            $options = $accessor->getProperty('options');

            expect($options['unsigned'])->toBeTrue();
        });
    });

    describe('MediumText', function () {
        it('constructs with MEDIUMTEXT type for MySQL', function () {
            DbPlatform::set(new Mysql());

            $column = new MediumText('content');
            $accessor = new ReflectionAccessor($column);

            expect($accessor->getProperty('type'))->toBe('MEDIUMTEXT');
        });

        it('constructs with MEDIUMTEXT type for SQLite', function () {
            DbPlatform::set(new Sqlite());

            $column = new MediumText('content');
            $accessor = new ReflectionAccessor($column);

            expect($accessor->getProperty('type'))->toBe('MEDIUMTEXT');
        });

        it('constructs with TEXT type for PostgreSQL', function () {
            DbPlatform::set(new Postgresql());

            $column = new MediumText('content');
            $accessor = new ReflectionAccessor($column);

            expect($accessor->getProperty('type'))->toBe('TEXT');
        });

        it('constructs with MEDIUMTEXT type when no platform set', function () {
            $column = new MediumText('content');
            $accessor = new ReflectionAccessor($column);

            expect($accessor->getProperty('type'))->toBe('MEDIUMTEXT');
        });

        it('constructs with name', function () {
            $column = new MediumText('description');

            expect($column->getName())->toBe('description');
        });

        it('constructs with nullable', function () {
            $column = new MediumText('content', null, true);

            expect($column->isNullable())->toBeTrue();
        });

        it('constructs with default value', function () {
            $column = new MediumText('content', null, false, 'default text');

            expect($column->getDefault())->toBe('default text');
        });
    });

    describe('AutoIncrementInteger', function () {
        it('constructs with autoincrement for MySQL', function () {
            DbPlatform::set(new Mysql());

            $column = new AutoIncrementInteger();
            $accessor = new ReflectionAccessor($column);

            $options = $accessor->getProperty('options');

            expect($options['autoincrement'])->toBeTrue();
        });

        it('constructs with autoincrement for SQLite', function () {
            DbPlatform::set(new Sqlite());

            $column = new AutoIncrementInteger();
            $accessor = new ReflectionAccessor($column);

            $options = $accessor->getProperty('options');

            expect($options['autoincrement'])->toBeTrue();
        });

        it('constructs with SERIAL type for PostgreSQL', function () {
            DbPlatform::set(new Postgresql());

            $column = new AutoIncrementInteger();
            $accessor = new ReflectionAccessor($column);

            expect($accessor->getProperty('type'))->toBe('SERIAL');
        });

        it('does not set autoincrement option for PostgreSQL', function () {
            DbPlatform::set(new Postgresql());

            $column = new AutoIncrementInteger();
            $accessor = new ReflectionAccessor($column);

            $options = $accessor->getProperty('options');

            expect($options)->not->toHaveKey('autoincrement');
        });

        it('constructs with autoincrement when no platform set', function () {
            $column = new AutoIncrementInteger();
            $accessor = new ReflectionAccessor($column);

            $options = $accessor->getProperty('options');

            expect($options['autoincrement'])->toBeTrue();
        });

        it('constructs with default name', function () {
            DbPlatform::set(new Mysql());

            $column = new AutoIncrementInteger();

            expect($column->getName())->toBe('id');
        });

        it('constructs with custom name', function () {
            DbPlatform::set(new Mysql());

            $column = new AutoIncrementInteger('user_id');

            expect($column->getName())->toBe('user_id');
        });

        it('inherits unsigned option from parent', function () {
            DbPlatform::set(new Mysql());

            $column = new AutoIncrementInteger();
            $accessor = new ReflectionAccessor($column);

            $options = $accessor->getProperty('options');

            expect($options['unsigned'])->toBeTrue();
        });

        it('merges custom options for MySQL', function () {
            DbPlatform::set(new Mysql());

            $column = new AutoIncrementInteger('id', false, null, ['custom' => 'value']);
            $accessor = new ReflectionAccessor($column);

            $options = $accessor->getProperty('options');

            expect($options['autoincrement'])->toBeTrue()
                ->and($options['unsigned'])->toBeTrue()
                ->and($options['custom'])->toBe('value');
        });
    });
});

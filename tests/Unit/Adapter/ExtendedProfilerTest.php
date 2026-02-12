<?php

declare(strict_types=1);

use Laminas\Db\Adapter\ParameterContainer;
use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Adapter\StatementContainerInterface;
use Laminas\Db\Extra\Adapter\ExtendedProfiler;
use Laminas\Db\Sql\Exception\InvalidArgumentException;
use Tests\ReflectionAccessor;

describe('ExtendedProfiler', function () {
    it('constructs with platform', function () {
        $platform = mock(PlatformInterface::class);
        $profiler = new ExtendedProfiler($platform);

        expect($profiler)->toBeInstanceOf(ExtendedProfiler::class);
    });

    it('constructs without platform', function () {
        $profiler = new ExtendedProfiler();

        expect($profiler)->toBeInstanceOf(ExtendedProfiler::class);
    });

    it('starts profiling with string SQL', function () {
        $profiler = new ExtendedProfiler();

        $sql = /** @lang text */ 'SELECT * FROM users';

        $result = $profiler->profilerStart($sql);

        expect($result)->toBe($profiler)
            ->and($profiler->getProfiles())->toHaveCount(1);
    });

    it('starts profiling with StatementContainerInterface without platform', function () {
        $profiler = new ExtendedProfiler();

        $paramContainer = new ParameterContainer([]);

        $statement = mock(StatementContainerInterface::class);
        $statement->shouldReceive('getSql')->andReturn(/** @lang text */ 'SELECT * FROM users');
        $statement->shouldReceive('getParameterContainer')->andReturn($paramContainer);

        $result = $profiler->profilerStart($statement);

        expect($result)->toBe($profiler)
            ->and($profiler->getProfiles())->toHaveCount(1)
            ->and($profiler->getProfiles()[0]['sql'])->toBe(/** @lang text */ 'SELECT * FROM users');
    });

    it('starts profiling with StatementContainerInterface with platform', function () {
        $platform = mock(PlatformInterface::class);
        $platform->shouldReceive('quoteTrustedValue')->andReturn("'1'");
        $profiler = new ExtendedProfiler($platform);

        $paramContainer = new ParameterContainer(['id' => 1]);

        $statement = mock(StatementContainerInterface::class);
        $statement->shouldReceive('getSql')->andReturn(/** @lang text */ 'SELECT * FROM users WHERE id = :id');
        $statement->shouldReceive('getParameterContainer')->andReturn($paramContainer);

        $result = $profiler->profilerStart($statement);

        expect($result)->toBe($profiler)
            ->and($profiler->getProfiles())->toHaveCount(1);
    });

    it('throws exception for invalid target', function () {
        $profiler = new ExtendedProfiler();

        expect(fn() => $profiler->profilerStart(123))->toThrow(InvalidArgumentException::class);
    });

    it('handles null parameter values in statement', function () {
        $platform = mock(PlatformInterface::class);
        $platform->shouldReceive('quoteTrustedValue')->andReturn("'test'");
        $profiler = new ExtendedProfiler($platform);

        $paramContainer = new ParameterContainer(['id' => 1, 'name' => null]);

        $statement = mock(StatementContainerInterface::class);
        $statement->shouldReceive('getSql')->andReturn(/** @lang text */ 'SELECT * FROM users WHERE id = :id AND name = :name');
        $statement->shouldReceive('getParameterContainer')->andReturn($paramContainer);

        $result = $profiler->profilerStart($statement);

        expect($result)->toBe($profiler)
            ->and($profiler->getProfiles())->toHaveCount(1)
            ->and($profiler->getProfiles()[0]['sql'])->toContain('NULL');
    });

    it('captures backtrace with relevant frame when called from class method', function () {
        $profiler = new ExtendedProfiler();

        $sql = /** @lang text */ 'SELECT * FROM users';

        $profiler->profilerStart($sql);

        $profiles = $profiler->getProfiles();
        expect($profiles[0]['backtrace'])->not->toBeNull();
    });

    it('returns formatted SQL (simple case)', function () {
        $source /** @lang text */
            = <<<SQL
            SELECT smf_lp_plugins.name AS name, smf_lp_plugins.config AS config, smf_lp_plugins.value AS value FROM smf_lp_plugins
            SQL;

        $formatted /** @lang text */
            = <<<SQL
            SELECT
                smf_lp_plugins.name AS name,
                smf_lp_plugins.config AS config,
                smf_lp_plugins.value AS value
            FROM smf_lp_plugins
            SQL;

        $accessor = new ReflectionAccessor(new ExtendedProfiler());
        $result = $accessor->callMethod('formatSql', [$source]);

        expect($result)->toBe($formatted);
    });

    it('returns formatted SQL (complex case)', function () {
        $source /** @lang text */
            = <<<SQL
            SELECT b.*, COALESCE(NULLIF(t.title, ""), tf.title, "") AS title, COALESCE(NULLIF(t.content, ""), tf.content, "") AS content, pp.name AS name, pp.value AS value FROM smf_lp_blocks AS b LEFT JOIN smf_lp_translations AS t ON t.item_id = b.block_id AND t.type = 'block' AND t.lang = 'russian' LEFT JOIN smf_lp_translations AS tf ON tf.item_id = b.block_id AND tf.type = 'block' AND tf.lang = 'english' LEFT JOIN smf_lp_params AS pp ON pp.item_id = b.block_id AND pp.type = 'block' WHERE status = '1' ORDER BY placement DESC, priority ASC
            SQL;

        $formatted /** @lang text */
            = <<<SQL
            SELECT
                b.*,
                COALESCE(NULLIF(t.title, ""), tf.title, "") AS title,
                COALESCE(NULLIF(t.content, ""), tf.content, "") AS content,
                pp.name AS name,
                pp.value AS value
            FROM smf_lp_blocks AS b
            LEFT JOIN smf_lp_translations AS t ON t.item_id = b.block_id
                AND t.type = 'block'
                AND t.lang = 'russian'
            LEFT JOIN smf_lp_translations AS tf ON tf.item_id = b.block_id
                AND tf.type = 'block'
                AND tf.lang = 'english'
            LEFT JOIN smf_lp_params AS pp ON pp.item_id = b.block_id
                AND pp.type = 'block'
            WHERE status = '1'
            ORDER BY placement DESC, priority ASC
            SQL;

        $accessor = new ReflectionAccessor(new ExtendedProfiler());
        $result = $accessor->callMethod('formatSql', [$source]);

        expect($result)->toBe($formatted);
    });

    it('returns formatted SQL (complex case with subqueries)', function () {
        $source /** @lang text */
            = <<<SQL
            SELECT p.page_id AS page_id, p.slug AS slug, p.permissions AS permissions, pp2.value AS icon, (SELECT smf_lp_translations.title AS title FROM smf_lp_translations WHERE item_id = p.page_id AND type = 'page' AND lang IN ('russian', 'english') ORDER BY lang = 'russian' DESC LIMIT 1) AS page_title FROM smf_lp_pages AS p LEFT JOIN smf_lp_params AS pp ON pp.item_id = p.page_id AND pp.type = 'page' AND pp.name = 'show_in_menu' LEFT JOIN smf_lp_params AS pp2 ON pp2.item_id = p.page_id AND pp2.type = 'page' AND pp2.name = 'page_icon' WHERE p.status = '1' AND p.deleted_at = '0' AND p.created_at <= '1761586699' AND pp.value = '1' AND p.entry_type IN ('default', 'internal') AND EXISTS (SELECT 1 FROM smf_lp_translations WHERE item_id = p.page_id AND type = 'page' AND lang IN ('russian', 'english') AND (title IS NOT NULL AND title != ''))
            SQL;

        $formatted /** @lang text */
            = <<<SQL
            SELECT
                p.page_id AS page_id,
                p.slug AS slug,
                p.permissions AS permissions,
                pp2.value AS icon,
                (
                    SELECT smf_lp_translations.title AS title
                    FROM smf_lp_translations
                    WHERE item_id = p.page_id
                        AND type = 'page'
                        AND lang IN ('russian', 'english')
                    ORDER BY lang = 'russian' DESC
                    LIMIT 1
                ) AS page_title
            FROM smf_lp_pages AS p
            LEFT JOIN smf_lp_params AS pp ON pp.item_id = p.page_id
                AND pp.type = 'page'
                AND pp.name = 'show_in_menu'
            LEFT JOIN smf_lp_params AS pp2 ON pp2.item_id = p.page_id
                AND pp2.type = 'page'
                AND pp2.name = 'page_icon'
            WHERE p.status = '1'
                AND p.deleted_at = '0'
                AND p.created_at <= '1761586699'
                AND pp.value = '1'
                AND p.entry_type IN ('default', 'internal')
                AND EXISTS (
                    SELECT 1
                    FROM smf_lp_translations
                    WHERE item_id = p.page_id
                        AND type = 'page'
                        AND lang IN ('russian', 'english')
                        AND (title IS NOT NULL AND title != '')
                )
            SQL;

        $accessor = new ReflectionAccessor(new ExtendedProfiler());
        $result = $accessor->callMethod('formatSql', [$source]);

        expect($result)->toBe($formatted);
    });

    it('handles SQL with LIMIT and OFFSET without quotes', function () {
        $source /** @lang text */
            = 'SELECT * FROM users LIMIT 10 OFFSET 20';

        $accessor = new ReflectionAccessor(new ExtendedProfiler());
        $result = $accessor->callMethod('formatSql', [$source]);

        expect($result)->toContain('LIMIT 10')
            ->and($result)->toContain('OFFSET 20');
    });

    it('handles SQL with LIMIT and OFFSET with quotes', function () {
        $source /** @lang text */
            = "SELECT * FROM users LIMIT '10' OFFSET '20'";

        $accessor = new ReflectionAccessor(new ExtendedProfiler());
        $result = $accessor->callMethod('formatSql', [$source]);

        expect($result)->toContain("LIMIT '10'")
            ->and($result)->toContain("OFFSET '20'");
    });

    it('handles SQL with unclosed subquery parenthesis', function () {
        $source /** @lang text */
            = 'SELECT * FROM users WHERE id IN (SELECT id FROM posts';

        $accessor = new ReflectionAccessor(new ExtendedProfiler());
        $result = $accessor->callMethod('formatSql', [$source]);

        expect($result)->toContain('SELECT');
    });

    it('ignores profiler-related frames', function () {
        $profiler = new ExtendedProfiler();
        $accessor = new ReflectionAccessor($profiler);

        $frame = ['file' => '/app/MyProfiler.php', 'function' => 'test', 'class' => 'Test'];
        expect($accessor->callMethod('isFrameIgnored', [$frame]))->toBeTrue();
    });

    it('ignores system paths', function () {
        $profiler = new ExtendedProfiler();
        $accessor = new ReflectionAccessor($profiler);

        $frame = ['file' => '/usr/share/php/test.php', 'function' => 'test', 'class' => 'Test'];
        expect($accessor->callMethod('isFrameIgnored', [$frame]))->toBeTrue();
    });

    it('ignores phar paths', function () {
        $profiler = new ExtendedProfiler();
        $accessor = new ReflectionAccessor($profiler);

        $frame = ['file' => 'phar://vendor/test.php', 'function' => 'test', 'class' => 'Test'];
        expect($accessor->callMethod('isFrameIgnored', [$frame]))->toBeTrue();
    });

    it('does not ignore relevant frames', function () {
        $profiler = new ExtendedProfiler();
        $accessor = new ReflectionAccessor($profiler);

        $frame = ['file' => '/app/MyClass.php', 'function' => 'myMethod', 'class' => 'MyClass'];
        expect($accessor->callMethod('isFrameIgnored', [$frame]))->toBeFalse();
    });

    it('considers frame relevant when it has file and class', function () {
        $profiler = new ExtendedProfiler();
        $accessor = new ReflectionAccessor($profiler);

        $frame = ['file' => '/app/Test.php', 'class' => 'TestClass'];
        expect($accessor->callMethod('isFrameRelevant', [$frame]))->toBeTrue();
    });

    it('considers frame irrelevant when it has no class', function () {
        $profiler = new ExtendedProfiler();
        $accessor = new ReflectionAccessor($profiler);

        $frame = ['file' => '/app/functions.php', 'function' => 'myFunction'];
        expect($accessor->callMethod('isFrameRelevant', [$frame]))->toBeFalse();
    });

    it('returns null backtrace when all frames are irrelevant', function () {
        $profiler = new class () extends ExtendedProfiler {
            protected function isFrameRelevant(array $frame): bool
            {
                return false;
            }
        };

        $accessor = new ReflectionAccessor($profiler);
        $backtrace = $accessor->callMethod('captureBacktrace');

        expect($backtrace)->toBeNull();
    });

    it('formats short SELECT without line breaks', function () {
        $source /** @lang text */
            = 'SELECT id, name FROM users';

        $accessor = new ReflectionAccessor(new ExtendedProfiler());
        $result = $accessor->callMethod('formatSql', [$source]);

        expect($result)->toContain('SELECT id, name');
    });

    it('formats SELECT with single column', function () {
        $source /** @lang text */
            = 'SELECT id FROM users';

        $accessor = new ReflectionAccessor(new ExtendedProfiler());
        $result = $accessor->callMethod('formatSql', [$source]);

        expect($result)->toContain('SELECT id');
    });

    it('formats SQL with GROUP BY keyword', function () {
        $source /** @lang text */
            = 'SELECT id, name FROM users GROUP BY name';

        $accessor = new ReflectionAccessor(new ExtendedProfiler());
        $result = $accessor->callMethod('formatSql', [$source]);

        expect($result)->toContain('GROUP BY name');
    });

    it('formats SQL with HAVING keyword', function () {
        $source /** @lang text */
            = 'SELECT id, COUNT(*) as count FROM users GROUP BY id HAVING count > 1';

        $accessor = new ReflectionAccessor(new ExtendedProfiler());
        $result = $accessor->callMethod('formatSql', [$source]);

        expect($result)->toContain('HAVING count > 1');
    });

    it('formats SQL with RIGHT JOIN keyword', function () {
        $source /** @lang text */
            = 'SELECT u.id, p.name FROM users u RIGHT JOIN posts p ON u.id = p.user_id';

        $accessor = new ReflectionAccessor(new ExtendedProfiler());
        $result = $accessor->callMethod('formatSql', [$source]);

        expect($result)->toContain('RIGHT JOIN posts p');
    });

    it('formats SQL with INNER JOIN keyword', function () {
        $source /** @lang text */
            = 'SELECT u.id, p.name FROM users u INNER JOIN posts p ON u.id = p.user_id';

        $accessor = new ReflectionAccessor(new ExtendedProfiler());
        $result = $accessor->callMethod('formatSql', [$source]);

        expect($result)->toContain('INNER JOIN posts p');
    });

    it('formats SQL with OUTER JOIN keyword', function () {
        $source /** @lang text */
            = 'SELECT u.id, p.name FROM users u LEFT OUTER JOIN posts p ON u.id = p.user_id';

        $accessor = new ReflectionAccessor(new ExtendedProfiler());
        $result = $accessor->callMethod('formatSql', [$source]);

        expect($result)->toContain('OUTER JOIN posts p');
    });

    it('formats SQL with CROSS JOIN keyword', function () {
        $source /** @lang text */
            = 'SELECT u.id, p.name FROM users u CROSS JOIN posts p';

        $accessor = new ReflectionAccessor(new ExtendedProfiler());
        $result = $accessor->callMethod('formatSql', [$source]);

        expect($result)->toContain('CROSS JOIN posts p');
    });
});

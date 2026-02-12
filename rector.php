<?php declare(strict_types=1);

use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\Config\RectorConfig;
use Rector\Exception\Configuration\InvalidConfigurationException;

try {
    return RectorConfig::configure()
        ->withPaths([
            __DIR__ . '/src',
        ])
        ->withSkip([
            CatchExceptionNameMatchingTypeRector::class,
        ])
        ->withPhpSets()
        ->withTypeCoverageLevel(10)
        ->withDeadCodeLevel(10)
        ->withCodeQualityLevel(10)
        ->withCodingStyleLevel(9);
} catch (InvalidConfigurationException $e) {
    echo $e->getMessage();
}

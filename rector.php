 <?php

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use Rector\Set\ValueObject\LevelSetList;

return RectorConfig::configure()
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPreparedSets(doctrineCodeQuality: true)
    ->withSets([
        LevelSetList::UP_TO_PHP_84
    ])
    ->withAttributesSets(doctrine: true)
    ->withTypeCoverageLevel(3)
    ->withParallel(240, 8, 5);

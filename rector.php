 <?php

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;
use Rector\Set\ValueObject\LevelSetList;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;

return RectorConfig::configure()
      ->withSkip([PreferPHPUnitThisCallRector::class])
    ->withPhpVersion(PhpVersion::PHP_84)
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPreparedSets(doctrineCodeQuality: true)
    ->withSets([
        LevelSetList::UP_TO_PHP_84,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        PHPUnitSetList::PHPUNIT_120,
        PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES
    ])
    ->withAttributesSets(doctrine: true)
    ->withTypeCoverageLevel(3)
    ->withParallel(240, 8, 5);

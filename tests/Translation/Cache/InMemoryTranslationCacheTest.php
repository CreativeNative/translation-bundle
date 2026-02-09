<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Translation\Cache\InMemoryTranslationCache;
use Tmi\TranslationBundle\ValueObject\Tuuid;

#[CoversClass(InMemoryTranslationCache::class)]
final class InMemoryTranslationCacheTest extends TestCase
{
    private InMemoryTranslationCache $cache;

    protected function setUp(): void
    {
        $this->cache = new InMemoryTranslationCache();
    }

    public function testHasReturnsFalseWhenEmpty(): void
    {
        self::assertFalse($this->cache->has('some-tuuid', 'en'));
    }

    public function testSetAndHasReturnsTrue(): void
    {
        $entity = $this->createEntity('en');

        $this->cache->set('tuuid-1', 'en', $entity);

        self::assertTrue($this->cache->has('tuuid-1', 'en'));
    }

    public function testGetReturnsNullWhenNotCached(): void
    {
        self::assertNull($this->cache->get('tuuid-1', 'en'));
    }

    public function testSetAndGetReturnsEntity(): void
    {
        $entity = $this->createEntity('en');

        $this->cache->set('tuuid-1', 'en', $entity);

        self::assertSame($entity, $this->cache->get('tuuid-1', 'en'));
    }

    public function testSetOverwritesPreviousEntry(): void
    {
        $first  = $this->createEntity('en');
        $second = $this->createEntity('en');

        $this->cache->set('tuuid-1', 'en', $first);
        $this->cache->set('tuuid-1', 'en', $second);

        self::assertSame($second, $this->cache->get('tuuid-1', 'en'));
    }

    public function testHasReturnsFalseForDifferentLocale(): void
    {
        $entity = $this->createEntity('en');

        $this->cache->set('tuuid-1', 'en', $entity);

        self::assertFalse($this->cache->has('tuuid-1', 'de'));
    }

    public function testMarkInProgressAndIsInProgress(): void
    {
        $this->cache->markInProgress('tuuid-1', 'en');

        self::assertTrue($this->cache->isInProgress('tuuid-1', 'en'));
    }

    public function testIsInProgressReturnsFalseByDefault(): void
    {
        self::assertFalse($this->cache->isInProgress('tuuid-1', 'en'));
    }

    public function testUnmarkInProgress(): void
    {
        $this->cache->markInProgress('tuuid-1', 'en');
        $this->cache->unmarkInProgress('tuuid-1', 'en');

        self::assertFalse($this->cache->isInProgress('tuuid-1', 'en'));
    }

    public function testInProgressGranularityIsTuuidPlusLocale(): void
    {
        $this->cache->markInProgress('tuuid-1', 'en');

        self::assertFalse($this->cache->isInProgress('tuuid-1', 'de'));
    }

    public function testUnmarkInProgressDoesNothingWhenNotMarked(): void
    {
        $this->cache->unmarkInProgress('tuuid-1', 'en');

        self::assertFalse($this->cache->isInProgress('tuuid-1', 'en'));
    }

    private function createEntity(string $locale): Scalar
    {
        $entity = new Scalar();
        $entity->setTuuid(Tuuid::generate());
        $entity->setLocale($locale);

        return $entity;
    }
}

<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Translation\Cache\Psr6TranslationCache;
use Tmi\TranslationBundle\ValueObject\Tuuid;

#[CoversClass(Psr6TranslationCache::class)]
final class Psr6TranslationCacheTest extends TestCase
{
    private ArrayAdapter $pool;
    private Psr6TranslationCache $cache;

    protected function setUp(): void
    {
        $this->pool  = new ArrayAdapter();
        $this->cache = new Psr6TranslationCache($this->pool);
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

        $retrieved = $this->cache->get('tuuid-1', 'en');
        self::assertInstanceOf(Scalar::class, $retrieved);
        self::assertSame($entity->getTuuid()->getValue(), $retrieved->getTuuid()->getValue());
        self::assertSame($entity->getLocale(), $retrieved->getLocale());
    }

    public function testSetOverwritesPreviousEntry(): void
    {
        $first  = $this->createEntity('en');
        $second = $this->createEntity('en');

        $this->cache->set('tuuid-1', 'en', $first);
        $this->cache->set('tuuid-1', 'en', $second);

        $retrieved = $this->cache->get('tuuid-1', 'en');
        self::assertInstanceOf(Scalar::class, $retrieved);
        self::assertSame($second->getTuuid()->getValue(), $retrieved->getTuuid()->getValue());
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

    public function testGetReturnsNullWhenCachedValueIsNotTranslatable(): void
    {
        // Manually put a non-TranslatableInterface value into the pool
        $key  = 'tmi_translation.tuuid_1.en';
        $item = $this->pool->getItem($key);
        $item->set('not-a-translatable-entity');
        $this->pool->save($item);

        self::assertNull($this->cache->get('tuuid-1', 'en'));
    }

    public function testKeyFormatReplacesUuidDashes(): void
    {
        $entity         = $this->createEntity('en');
        $uuidWithDashes = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

        // If dashes were NOT replaced, PSR-6 would throw InvalidArgumentException
        $this->cache->set($uuidWithDashes, 'en', $entity);

        self::assertTrue($this->cache->has($uuidWithDashes, 'en'));
        $retrieved = $this->cache->get($uuidWithDashes, 'en');
        self::assertInstanceOf(Scalar::class, $retrieved);
        self::assertSame($entity->getTuuid()->getValue(), $retrieved->getTuuid()->getValue());
    }

    private function createEntity(string $locale): Scalar
    {
        $entity = new Scalar();
        $entity->setTuuid(Tuuid::generate());
        $entity->setLocale($locale);

        return $entity;
    }
}

<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation\Cache;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Translation\Cache\Psr6TranslationCache;
use Tmi\TranslationBundle\ValueObject\Tuuid;

#[CoversClass(Psr6TranslationCache::class)]
final class Psr6TranslationCacheTest extends TestCase
{
    private ArrayAdapter $pool;

    private MockObject&EntityManagerInterface $entityManager;

    /** @var Stub&ClassMetadata<object> */
    private Stub&ClassMetadata $metadata;

    private Psr6TranslationCache $cache;

    /**
     * Identifier that getIdentifierValues() reports for an entity created via
     * createEntity()/createUnpersistedEntity(), keyed by spl_object_id(). A single
     * callback stub (below) reads this instead of stacking per-entity with() stubs on the
     * mock, which PHPUnit does not disambiguate by argument across separate registrations.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $identifiers = [];

    protected function setUp(): void
    {
        $this->pool          = new ArrayAdapter();
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->metadata      = static::createStub(ClassMetadata::class);
        $this->metadata->method('getIdentifierValues')
            ->willReturnCallback(fn (object $entity): array => $this->identifiers[spl_object_id($entity)] ?? []);
        $this->entityManager->method('getClassMetadata')->with(Scalar::class)->willReturn($this->metadata);

        $this->cache = new Psr6TranslationCache($this->pool, $this->entityManager);
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

    public function testGetReloadsTheEntityThroughTheEntityManager(): void
    {
        $entity     = $this->createEntity('en');
        $identifier = ['id' => spl_object_id($entity)];

        $this->entityManager->expects(self::once())
            ->method('find')
            ->with(Scalar::class, $identifier)
            ->willReturn($entity);

        $this->cache->set('tuuid-1', 'en', $entity);

        $retrieved = $this->cache->get('tuuid-1', 'en');
        self::assertSame($entity, $retrieved);
    }

    public function testGetReturnsNullWhenTheReferencedRowIsGone(): void
    {
        // set() cached a valid [class, id] reference, but the row it points at was
        // deleted before get() reloaded it -- a clean cache miss, not a stale object.
        $entity = $this->createEntity('en');

        $this->cache->set('tuuid-1', 'en', $entity);

        $this->entityManager->method('find')->willReturn(null);

        self::assertTrue($this->cache->has('tuuid-1', 'en'), 'the pool entry itself is still present');
        self::assertNull($this->cache->get('tuuid-1', 'en'));
    }

    public function testSetSkipsCachingAnEntityWithoutAnIdentifierYet(): void
    {
        // Not persisted (or persisted but not flushed): getIdentifierValues() returns [].
        $entity = $this->createUnpersistedEntity('en');

        $this->entityManager->expects(self::never())->method('find');

        $this->cache->set('tuuid-1', 'en', $entity);

        self::assertFalse($this->cache->has('tuuid-1', 'en'));
        self::assertNull($this->cache->get('tuuid-1', 'en'));
    }

    public function testSetOverwritesPreviousEntry(): void
    {
        $first  = $this->createEntity('en');
        $second = $this->createEntity('en');

        $this->cache->set('tuuid-1', 'en', $first);
        $this->cache->set('tuuid-1', 'en', $second);

        $this->entityManager->expects(self::once())
            ->method('find')
            ->with(Scalar::class, ['id' => spl_object_id($second)])
            ->willReturn($second);

        $retrieved = $this->cache->get('tuuid-1', 'en');
        self::assertSame($second, $retrieved);
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

    public function testMarkInProgressSetsExpiryOnTheCacheItem(): void
    {
        // Defence in depth for persistent pools: a marker that somehow survives its frame
        // must expire on its own instead of blocking that tuuid/locale forever.
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects($this->once())->method('set')->with(true)->willReturnSelf();
        $item->expects($this->once())->method('expiresAfter')->with(60)->willReturnSelf();

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects($this->once())->method('getItem')->with('tmi_in_progress.tuuid_1.en')->willReturn($item);
        $pool->expects($this->once())->method('save')->with($item)->willReturn(true);

        (new Psr6TranslationCache($pool, $this->entityManager))->markInProgress('tuuid-1', 'en');
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

    public function testGetReturnsNullWhenCachedValueIsNotAValidReference(): void
    {
        // A pre-3.2 cache entry (or any other unrecognised value) is not an [class, id]
        // array -- get() must treat it as a miss instead of erroring.
        $key  = 'tmi_translation.tuuid_1.en';
        $item = $this->pool->getItem($key);
        $item->set('not-a-valid-reference');
        $this->pool->save($item);

        $this->entityManager->expects(self::never())->method('find');

        self::assertNull($this->cache->get('tuuid-1', 'en'));
    }

    public function testKeyFormatReplacesUuidDashes(): void
    {
        $entity         = $this->createEntity('en');
        $uuidWithDashes = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

        $this->entityManager->method('find')
            ->with(Scalar::class, ['id' => spl_object_id($entity)])
            ->willReturn($entity);

        // If dashes were NOT replaced, PSR-6 would throw InvalidArgumentException
        $this->cache->set($uuidWithDashes, 'en', $entity);

        self::assertTrue($this->cache->has($uuidWithDashes, 'en'));
        $retrieved = $this->cache->get($uuidWithDashes, 'en');
        self::assertSame($entity, $retrieved);
    }

    private function createEntity(string $locale): Scalar
    {
        $entity = new Scalar();
        $entity->setTuuid(Tuuid::generate());
        $entity->setLocale($locale);

        $this->identifiers[spl_object_id($entity)] = ['id' => spl_object_id($entity)];

        return $entity;
    }

    private function createUnpersistedEntity(string $locale): Scalar
    {
        // No entry recorded in $this->identifiers: the callback stub falls back to [],
        // matching an entity Doctrine has not assigned an identifier to yet.
        $entity = new Scalar();
        $entity->setTuuid(Tuuid::generate());
        $entity->setLocale($locale);

        return $entity;
    }
}

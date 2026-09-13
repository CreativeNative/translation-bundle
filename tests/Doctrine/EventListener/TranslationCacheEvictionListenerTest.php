<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Doctrine\EventListener\TranslationCacheEvictionListener;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Translation\Cache\InMemoryTranslationCache;
use Tmi\TranslationBundle\ValueObject\Tuuid;

#[CoversClass(TranslationCacheEvictionListener::class)]
final class TranslationCacheEvictionListenerTest extends TestCase
{
    public function testARemovedTranslatableIsEvictedUnderItsTuuidAndLocale(): void
    {
        $tuuid = Tuuid::generate();
        $cache = new InMemoryTranslationCache();
        $de    = new Scalar()->setTuuid($tuuid)->setLocale('de_DE');
        $it    = new Scalar()->setTuuid($tuuid)->setLocale('it_IT');
        $cache->set($tuuid->getValue(), 'de_DE', $de);
        $cache->set($tuuid->getValue(), 'it_IT', $it);

        new TranslationCacheEvictionListener($cache)->postRemove($this->postRemove($de));

        self::assertNull($cache->get($tuuid->getValue(), 'de_DE'));
        self::assertSame($it, $cache->get($tuuid->getValue(), 'it_IT'), 'only the removed locale is forgotten');
    }

    public function testANonTranslatableEntityIsIgnored(): void
    {
        $tuuid = Tuuid::generate();
        $cache = new InMemoryTranslationCache();
        $cache->set($tuuid->getValue(), 'de_DE', new Scalar()->setTuuid($tuuid)->setLocale('de_DE'));

        new TranslationCacheEvictionListener($cache)->postRemove($this->postRemove(new \stdClass()));

        self::assertNotNull($cache->get($tuuid->getValue(), 'de_DE'));
    }

    public function testATranslatableWithoutALocaleIsIgnored(): void
    {
        $tuuid = Tuuid::generate();
        $cache = new InMemoryTranslationCache();
        $cache->set($tuuid->getValue(), 'de_DE', new Scalar()->setTuuid($tuuid)->setLocale('de_DE'));

        // A row without a locale was never a cached translation: nothing to evict, nothing to guess.
        new TranslationCacheEvictionListener($cache)->postRemove($this->postRemove(new Scalar()->setTuuid($tuuid)));

        self::assertNotNull($cache->get($tuuid->getValue(), 'de_DE'));
    }

    private function postRemove(object $entity): PostRemoveEventArgs
    {
        return new PostRemoveEventArgs($entity, self::createStub(EntityManagerInterface::class));
    }
}

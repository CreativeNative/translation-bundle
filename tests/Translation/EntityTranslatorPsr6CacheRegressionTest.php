<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\Translation\Cache\Psr6TranslationCache;
use Tmi\TranslationBundle\Translation\EntityTranslator;
use Tmi\TranslationBundle\Translation\Handlers\TranslationHandlerInterface;
use Tmi\TranslationBundle\Translation\TypeDefaultResolver;

/**
 * Regression: on a PSR-6 pool, key presence and loadability can disagree — the
 * pool still holds the key while the entry no longer loads (row deleted after
 * caching, pre-3.2 entry format). The pre-3.2.1 check-then-get in
 * EntityTranslator (via the has() the interface carried until v3.4.0) handed
 * that null straight to translate(), whose \assert is compiled out under
 * zend.assertions=-1 (the production default), so the null escaped as
 * "TypeError: Return value must be of type TranslatableInterface, null
 * returned". A hit that cannot be loaded must be an ordinary miss.
 *
 * Unreachable with InMemoryTranslationCache (its entries cannot go stale),
 * which is why the regular suite never saw it — this test runs the real
 * pipeline against a real PSR-6 pool.
 */
final class EntityTranslatorPsr6CacheRegressionTest extends IntegrationTestCase
{
    public function testStalePsr6HitIsACleanMissNotATypeError(): void
    {
        $entityManager = $this->entityManager();
        // Psr6TranslationCache::get() reloads through EntityManager::find(); the locale
        // filter would turn that reload itself into a miss and blur what is under test.
        $entityManager->getFilters()->disable('tmi_translation_locale_filter');

        $pool       = new ArrayAdapter();
        $cache      = new Psr6TranslationCache($pool, $entityManager);
        $translator = $this->psr6Translator($cache);

        $source = new Scalar()->setLocale('en_US')->setTitle('EN');
        $entityManager->persist($source);
        $entityManager->flush();

        // First translate creates the variant. Nothing is cached yet: set() skips
        // entities without an identifier, and the variant is not flushed until here.
        $variant = $translator->translate($source, self::TARGET_LOCALE);
        self::assertInstanceOf(Scalar::class, $variant);
        $entityManager->persist($variant);
        $entityManager->flush();

        // Second translate: warmup finds the flushed row and caches [class, id].
        $tuuid   = $source->getTuuid()->getValue();
        $poolKey = 'tmi_translation.'.str_replace('-', '_', $tuuid).'.'.self::TARGET_LOCALE;
        self::assertSame($variant, $translator->translate($source, self::TARGET_LOCALE));
        self::assertTrue($pool->hasItem($poolKey), 'precondition: the pool holds the entry');

        // Delete the row behind the cache entry: the key survives, the entity is gone.
        $entityManager->remove($variant);
        $entityManager->flush();
        $entityManager->clear();

        self::assertTrue($pool->hasItem($poolKey), 'precondition: the pool still holds the key');
        self::assertNull($cache->get($tuuid, self::TARGET_LOCALE), 'precondition: the stale entry no longer loads');

        // Production shape: zend.assertions=-1 compiles \assert() away entirely, so the
        // escaping null fires translate()'s return type. -1 cannot be set at runtime;
        // 0 (assert calls skipped, not evaluated) is the closest reachable shape — with
        // assertions evaluated the pre-fix failure reads as an AssertionError instead,
        // which hides the production TypeError.
        $previous = ini_set('zend.assertions', '0');

        try {
            $fresh = $translator->translate($source, self::TARGET_LOCALE);
        } finally {
            if (false !== $previous) {
                ini_set('zend.assertions', $previous);
            }
        }

        self::assertInstanceOf(Scalar::class, $fresh);
        self::assertSame(self::TARGET_LOCALE, $fresh->getLocale());
        self::assertSame($tuuid, $fresh->getTuuid()->getValue());
        self::assertNull($fresh->getId(), 'The stale hit resolves to a fresh unmanaged variant');
    }

    /**
     * An EntityTranslator wired exactly like the service one — real EntityManager and
     * the DI-built handler chain — but with a PSR-6 cache instead of the in-memory one.
     * The cache is constructor-injected and readonly, so a separate instance is the
     * narrowest way to run the real pipeline against a PSR-6 pool.
     */
    private function psr6Translator(Psr6TranslationCache $cache): EntityTranslator
    {
        $translator = new EntityTranslator(
            'en_US',
            ['en_US', 'de_DE', 'it_IT'],
            true,
            new EventDispatcher(),
            $this->attributeHelper(),
            new TypeDefaultResolver(),
            $this->entityManager(),
            $cache,
        );

        $handlersProperty = new \ReflectionProperty(EntityTranslator::class, 'handlers');
        $handlers         = $handlersProperty->getValue($this->translator());
        self::assertIsArray($handlers);

        foreach ($handlers as $entry) {
            self::assertIsArray($entry);
            self::assertIsInt($entry['priority']);
            self::assertInstanceOf(TranslationHandlerInterface::class, $entry['handler']);
            $translator->addTranslationHandler($entry['handler'], $entry['priority']);
        }

        return $translator;
    }
}

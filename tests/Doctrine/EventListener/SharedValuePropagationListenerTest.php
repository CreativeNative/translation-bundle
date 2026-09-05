<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\EventListener;

use Doctrine\ORM\Events;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\AbstractLogger;
use Tmi\TranslationBundle\Doctrine\EventListener\SharedValuePropagationListener;
use Tmi\TranslationBundle\Doctrine\LocaleVariantFinder;
use Tmi\TranslationBundle\Doctrine\SharedValueSynchronizer;
use Tmi\TranslationBundle\Exception\SharedValueConflictException;
use Tmi\TranslationBundle\Fixtures\Entity\Embedded\EmbeddedSharedTranslatable;
use Tmi\TranslationBundle\Fixtures\Entity\Embedded\Translatable;
use Tmi\TranslationBundle\Fixtures\Entity\Inheritance\Sti\StiBook;
use Tmi\TranslationBundle\Fixtures\Entity\Inheritance\Sti\StiToy;
use Tmi\TranslationBundle\Fixtures\Entity\Removal\RemovableChild;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Entity\SharedDate\SharedDate;
use Tmi\TranslationBundle\Fixtures\Entity\SharedEnum\SharedEnum;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\NonTranslatableManyToOneBidirectionalChild;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToOneUnidirectional;
use Tmi\TranslationBundle\Fixtures\Enum\Priority;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\Test\Support\QueryCounter;
use Tmi\TranslationBundle\ValueObject\Tuuid;

/**
 * The opt-in flush-time invariant: an edit of a #[SharedAmongstTranslations]
 * value on ANY locale variant reaches every sibling inside the same flush().
 *
 * Every test asserts on a SIBLING row reloaded from a cleared EntityManager
 * (a managed instance would report the new value even if its row was never
 * written) -- with the listener disabled, these same assertions fail on the
 * sibling row, which is the red proof (see testDisabledLeavesSiblingsUntouched).
 *
 * The container's own listener instance is registered with the flag off
 * (TestKernel does not enable propagate_shared_on_flush); each test adds one
 * more instance with the flag it needs, exactly as LocaleVariantRemovalListenerTest
 * does for its own opt-in.
 */
#[CoversClass(SharedValuePropagationListener::class)]
final class SharedValuePropagationListenerTest extends IntegrationTestCase
{
    // ------------------------------------------------------------------
    // 1. The listing scenario: edit on a non-default locale, siblings follow
    // ------------------------------------------------------------------

    public function testAnEditOnANonDefaultLocaleReachesEverySiblingInTheSameFlush(): void
    {
        $this->enable();
        $tuuid    = Tuuid::generate();
        $variants = $this->seedScalars($tuuid, ['en_US' => 'price on request', 'de_DE' => 'price on request', 'it_IT' => 'price on request']);

        $variants['it_IT']->setShared('120000');
        $this->entityManager()->flush();

        foreach ($this->reloadScalars($tuuid) as $locale => $row) {
            self::assertSame('120000', $row->getShared(), sprintf('%s must carry the value edited on it_IT.', $locale));
        }
    }

    // ------------------------------------------------------------------
    // 2. Field-level, not row-level: an unshared edit touches no sibling
    // ------------------------------------------------------------------

    public function testAnUnsharedEditIssuesNoSiblingLookupAndNoSiblingUpdate(): void
    {
        $this->enable();
        $tuuid    = Tuuid::generate();
        $variants = $this->seedScalars($tuuid, ['en_US' => 'same', 'de_DE' => 'same', 'it_IT' => 'same']);

        $variants['de_DE']->setTitle('Neuer Titel');

        $this->counter()->reset();
        $this->entityManager()->flush();

        // Exactly the one UPDATE of the edited row: no SELECT for siblings, no sibling UPDATE.
        self::assertSame(1, $this->counter()->count());

        $rows = $this->reloadScalars($tuuid);
        self::assertSame('Title en_US', $rows['en_US']->getTitle());
        self::assertSame('Title it_IT', $rows['it_IT']->getTitle());
    }

    // ------------------------------------------------------------------
    // 3./4. Value objects are cloned, enums are not
    // ------------------------------------------------------------------

    public function testASharedValueObjectArrivesAsAnEqualButDistinctInstance(): void
    {
        $this->enable();
        $tuuid = Tuuid::generate();
        $date  = new \DateTimeImmutable('2026-09-05 10:00:00');

        $en = new SharedDate()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN')->setPublishedAt(new \DateTimeImmutable('2020-01-01'));
        $de = new SharedDate()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE')->setPublishedAt(new \DateTimeImmutable('2020-01-01'));
        $this->persistAll($en, $de);

        $en->setPublishedAt($date);
        $this->entityManager()->flush();

        self::assertEquals($date, $de->getPublishedAt());
        self::assertNotSame($en->getPublishedAt(), $de->getPublishedAt());

        $deId = $de->getId();
        self::assertNotNull($deId);
        $this->entityManager()->clear();
        $reloaded = $this->find(SharedDate::class, $deId);
        self::assertEquals($date, $reloaded->getPublishedAt());
    }

    public function testASharedEnumArrivesAsTheIdenticalCase(): void
    {
        $this->enable();
        $tuuid = Tuuid::generate();

        $en = new SharedEnum()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN')->setPriority(Priority::Low);
        $de = new SharedEnum()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE')->setPriority(Priority::Low);
        $this->persistAll($en, $de);

        $en->setPriority(Priority::High);
        $this->entityManager()->flush();

        $deId = $de->getId();
        self::assertNotNull($deId);
        $this->entityManager()->clear();
        self::assertSame(Priority::High, $this->find(SharedEnum::class, $deId)->getPriority());
    }

    // ------------------------------------------------------------------
    // 5. Embeddables: whole, class-level, inner
    // ------------------------------------------------------------------

    public function testAWholeSharedEmbeddablePropagatesWhileAnUnsharedEmbeddableDoesNot(): void
    {
        $this->enable();
        $tuuid = Tuuid::generate();

        $en = new Translatable()->setTuuid($tuuid)->setLocale('en_US');
        $en->getSharedAddress()?->setStreet('old')->setCity('Palermo');
        $en->getAddress()?->setStreet('en street');
        $de = new Translatable()->setTuuid($tuuid)->setLocale('de_DE');
        $de->getSharedAddress()?->setStreet('old')->setCity('Palermo');
        $de->getAddress()?->setStreet('de street');
        $this->persistAll($en, $de);

        $en->getSharedAddress()?->setStreet('Via Roma 1');
        $en->getAddress()?->setStreet('en street changed');
        $this->entityManager()->flush();

        $deId = $de->getId();
        self::assertNotNull($deId);
        $this->entityManager()->clear();
        $reloaded = $this->find(Translatable::class, $deId);

        $sharedAddress = $reloaded->getSharedAddress();
        self::assertNotNull($sharedAddress);
        self::assertSame('Via Roma 1', $sharedAddress->getStreet());
        self::assertSame('Palermo', $sharedAddress->getCity());
        self::assertSame('de street', $reloaded->getAddress()?->getStreet(), 'An unshared embeddable stays per locale.');
    }

    public function testClassLevelAndInnerEmbeddableSharingPropagateOnlyTheSharedInnerProperties(): void
    {
        $this->enable();
        $tuuid = Tuuid::generate();

        $en = new EmbeddedSharedTranslatable()->setTuuid($tuuid)->setLocale('en_US')->setTitle('EN');
        $en->getClassShared()->setSharedByDefault('old')->setOverriddenToEmpty('EN note');
        $en->getPropertyShared()->setReference('old')->setLabel('English label');
        $de = new EmbeddedSharedTranslatable()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('DE');
        $de->getClassShared()->setSharedByDefault('old')->setOverriddenToEmpty('DE note');
        $de->getPropertyShared()->setReference('old')->setLabel('Deutsches Label');
        $this->persistAll($en, $de);

        $en->getClassShared()->setSharedByDefault('new')->setOverriddenToEmpty('EN note changed');
        $en->getPropertyShared()->setReference('REF-2')->setLabel('English label changed');
        $this->entityManager()->flush();

        $deId = $de->getId();
        self::assertNotNull($deId);
        $this->entityManager()->clear();
        $reloaded = $this->find(EmbeddedSharedTranslatable::class, $deId);

        self::assertSame('new', $reloaded->getClassShared()->getSharedByDefault());
        self::assertSame('DE note', $reloaded->getClassShared()->getOverriddenToEmpty(), '#[EmptyOnTranslate] overrides the class-level sharing.');
        self::assertSame('REF-2', $reloaded->getPropertyShared()->getReference());
        self::assertSame('Deutsches Label', $reloaded->getPropertyShared()->getLabel(), 'An unshared inner property stays per locale.');
    }

    // ------------------------------------------------------------------
    // 6. Single-valued association to a non-translatable target
    // ------------------------------------------------------------------

    public function testASharedAssociationToANonTranslatableTargetPointsSiblingsAtTheSameRow(): void
    {
        $this->enable();
        $tuuid = Tuuid::generate();
        $child = new NonTranslatableManyToOneBidirectionalChild();

        $en = new TranslatableManyToOneUnidirectional()->setTuuid($tuuid)->setLocale('en_US');
        $de = new TranslatableManyToOneUnidirectional()->setTuuid($tuuid)->setLocale('de_DE');
        $this->persistAll($en, $de);

        $en->setSharedToNonTranslatable($child);
        $this->entityManager()->flush();

        self::assertSame($child, $de->getSharedToNonTranslatable());

        $deId = $de->getId();
        self::assertNotNull($deId);
        $this->entityManager()->clear();
        self::assertSame($child->getId(), $this->find(TranslatableManyToOneUnidirectional::class, $deId)->getSharedToNonTranslatable()?->getId());
    }

    // ------------------------------------------------------------------
    // 7. A shared column declared only on a concrete subclass (STI)
    // ------------------------------------------------------------------

    public function testASubclassOnlySharedColumnPropagatesAcrossTheHierarchyTable(): void
    {
        $this->enable();
        $tuuid = Tuuid::generate();

        $en = new StiBook();
        $en->setIsbn('978-OLD')->setTuuid($tuuid)->setLocale('en_US');
        $de = new StiBook();
        $de->setIsbn('978-OLD')->setTuuid($tuuid)->setLocale('de_DE');
        $this->persistAll($en, $de);

        $en->setIsbn('978-NEW');
        $this->entityManager()->flush();

        $deId = $de->getId();
        self::assertNotNull($deId);
        $this->entityManager()->clear();
        self::assertSame('978-NEW', $this->find(StiBook::class, $deId)->getIsbn());
    }

    // ------------------------------------------------------------------
    // 8. A sibling already scheduled with an unrelated change keeps it
    // ------------------------------------------------------------------

    public function testASiblingScheduledWithAnUnrelatedChangeKeepsItAndReceivesTheSharedValue(): void
    {
        $this->enable();
        $tuuid    = Tuuid::generate();
        $variants = $this->seedScalars($tuuid, ['en_US' => 'old', 'de_DE' => 'old']);

        $variants['de_DE']->setTitle('DE title changed');
        $variants['en_US']->setShared('new');
        $this->entityManager()->flush();

        $de = $this->reloadScalars($tuuid)['de_DE'];
        self::assertSame('DE title changed', $de->getTitle());
        self::assertSame('new', $de->getShared());
    }

    // ------------------------------------------------------------------
    // 9. A sibling that is an uninitialised proxy
    // ------------------------------------------------------------------

    public function testAnUninitialisedProxySiblingIsInitialisedAndReceivesTheValue(): void
    {
        $this->enable();
        $tuuid    = Tuuid::generate();
        $variants = $this->seedScalars($tuuid, ['en_US' => 'old', 'de_DE' => 'old']);
        $deId     = $variants['de_DE']->getId();
        self::assertNotNull($deId);
        $this->entityManager()->clear();

        $de = $this->entityManager()->getReference(Scalar::class, $deId);
        self::assertNotNull($de);
        $en = $this->reloadManaged($tuuid)['en_US'];

        $en->setShared('new');
        $this->entityManager()->flush();

        self::assertSame('new', $de->getShared());
        self::assertSame('new', $this->reloadScalars($tuuid)['de_DE']->getShared());
    }

    // ------------------------------------------------------------------
    // 10. A variant inserted in the same flush is created from the updated source
    // ------------------------------------------------------------------

    public function testAVariantInsertedInTheSameFlushIsCreatedFromTheUpdatedSource(): void
    {
        $this->enable();
        $tuuid    = Tuuid::generate();
        $variants = $this->seedScalars($tuuid, ['en_US' => 'at clone time']);
        $en       = $variants['en_US'];

        $de = $this->translator()->translateAndPersist($en, 'de_DE');
        self::assertInstanceOf(Scalar::class, $de);
        self::assertSame('at clone time', $de->getShared());

        $en->setShared('after clone');
        $this->entityManager()->flush();

        self::assertSame('after clone', $this->reloadScalars($tuuid)['de_DE']->getShared());
    }

    /**
     * An insertion's change set is its whole row, so a clone-time value cannot
     * be told apart from a deliberate edit: the updated source wins, no conflict.
     */
    public function testAnInsertedVariantNeverConflictsTheUpdatedSourceWins(): void
    {
        $this->enable();
        $tuuid    = Tuuid::generate();
        $variants = $this->seedScalars($tuuid, ['en_US' => 'at clone time']);
        $en       = $variants['en_US'];

        $de = $this->translator()->translateAndPersist($en, 'de_DE');
        self::assertInstanceOf(Scalar::class, $de);
        $de->setShared('mine');
        $en->setShared('theirs');
        $this->entityManager()->flush();

        self::assertSame('theirs', $this->reloadScalars($tuuid)['de_DE']->getShared());
    }

    // ------------------------------------------------------------------
    // 11. Conflict: two variants, one property, different values -> nothing written
    // ------------------------------------------------------------------

    public function testDifferentNewValuesOnTwoVariantsInOneFlushThrowAndWriteNothing(): void
    {
        $this->enable();
        $tuuid    = Tuuid::generate();
        $variants = $this->seedScalars($tuuid, ['en_US' => 'old', 'de_DE' => 'old', 'it_IT' => 'old']);

        $variants['en_US']->setShared('from en');
        $variants['de_DE']->setShared('from de');

        try {
            $this->entityManager()->flush();
            self::fail('Two different new values for one shared property must not flush.');
        } catch (SharedValueConflictException $exception) {
            self::assertStringContainsString(Scalar::class.'::$shared', $exception->getMessage());
            self::assertStringContainsString((string) $tuuid, $exception->getMessage());
            self::assertStringContainsString('"from en"', $exception->getMessage());
            self::assertStringContainsString('"from de"', $exception->getMessage());
            self::assertStringContainsString('en_US', $exception->getMessage());
            self::assertStringContainsString('de_DE', $exception->getMessage());
        }

        // The EntityManager is closed after a failed flush; the connection is not.
        $table = $this->entityManager()->getClassMetadata(Scalar::class)->getTableName();
        $rows  = $this->entityManager()->getConnection()->fetchFirstColumn(
            sprintf('SELECT shared FROM %s WHERE tuuid = ? ORDER BY locale', $table),
            [(string) $tuuid],
        );

        self::assertSame(['old', 'old', 'old'], $rows);
    }

    public function testTheSameNewValueOnTwoVariantsIsNotAConflictAndPropagatesOnce(): void
    {
        $logger   = $this->enable($this->spyLogger());
        $tuuid    = Tuuid::generate();
        $variants = $this->seedScalars($tuuid, ['en_US' => 'old', 'de_DE' => 'old', 'it_IT' => 'old']);

        $variants['en_US']->setShared('agreed');
        $variants['de_DE']->setShared('agreed');
        $this->entityManager()->flush();

        self::assertSame('agreed', $this->reloadScalars($tuuid)['it_IT']->getShared());
        self::assertCount(1, $logger->records, 'The second variant\'s change is the propagated one and must not propagate again.');
    }

    /**
     * The ping-pong guard is per (entity, path), not per entity: two variants
     * that each edit a DIFFERENT shared property in one flush both propagate.
     */
    public function testTwoVariantsEditingDifferentSharedPropertiesBothPropagate(): void
    {
        $this->enable();
        $tuuid = Tuuid::generate();

        $rows = [];
        foreach (['en_US', 'de_DE', 'it_IT'] as $locale) {
            $row = new EmbeddedSharedTranslatable()->setTuuid($tuuid)->setLocale($locale)->setTitle($locale);
            $row->getClassShared()->setSharedByDefault('old');
            $row->getPropertyShared()->setReference('old');
            $rows[$locale] = $row;
        }
        $this->persistAll(...array_values($rows));

        $rows['en_US']->getClassShared()->setSharedByDefault('A');
        $rows['de_DE']->getPropertyShared()->setReference('R');
        $this->entityManager()->flush();

        $ids = array_map(static fn (EmbeddedSharedTranslatable $row): int|null => $row->getId(), $rows);
        $this->entityManager()->clear();

        foreach ($ids as $locale => $id) {
            self::assertNotNull($id);
            $reloaded = $this->find(EmbeddedSharedTranslatable::class, $id);
            self::assertSame('A', $reloaded->getClassShared()->getSharedByDefault(), $locale);
            self::assertSame('R', $reloaded->getPropertyShared()->getReference(), $locale);
        }
    }

    // ------------------------------------------------------------------
    // 12. Flag off: v4.0.0 behaviour, byte-identical (the red proof of every test above)
    // ------------------------------------------------------------------

    public function testDisabledLeavesSiblingsUntouched(): void
    {
        $this->enable(null, false);
        $tuuid    = Tuuid::generate();
        $variants = $this->seedScalars($tuuid, ['en_US' => 'old', 'de_DE' => 'old', 'it_IT' => 'old']);

        $variants['it_IT']->setShared('120000');
        $this->entityManager()->flush();

        $rows = $this->reloadScalars($tuuid);
        self::assertSame('120000', $rows['it_IT']->getShared());
        self::assertSame('old', $rows['en_US']->getShared());
        self::assertSame('old', $rows['de_DE']->getShared());
    }

    // ------------------------------------------------------------------
    // 13. Exactly one propagation pass, no re-entry
    // ------------------------------------------------------------------

    public function testThreeLocalesOneEditIsOneLookupPlusThreeUpdatesAndOneLogLine(): void
    {
        $logger   = $this->enable($this->spyLogger());
        $tuuid    = Tuuid::generate();
        $variants = $this->seedScalars($tuuid, ['en_US' => 'old', 'de_DE' => 'old', 'it_IT' => 'old']);

        $variants['de_DE']->setShared('new');

        $this->counter()->reset();
        $this->entityManager()->flush();

        // 1 SELECT for the siblings + 3 UPDATEs (the edited row and both siblings).
        // A second propagation pass would add another SELECT.
        self::assertSame(4, $this->counter()->count());

        self::assertCount(1, $logger->records);
        self::assertSame('debug', $logger->records[0]['level']);
        self::assertSame(
            ['class' => Scalar::class, 'tuuid' => (string) $tuuid, 'locale' => 'de_DE', 'properties' => ['shared'], 'siblings' => 2],
            $logger->records[0]['context'],
        );
    }

    // ------------------------------------------------------------------
    // Entities the listener must leave alone
    // ------------------------------------------------------------------

    public function testANonTranslatableUpdateIsIgnored(): void
    {
        $this->enable();

        $child = new RemovableChild()->setName('before');
        $this->persistAll($child);

        $child->setName('after');

        $this->counter()->reset();
        $this->entityManager()->flush();

        self::assertSame(1, $this->counter()->count());
    }

    public function testATranslatableClassWithoutSharedPropertiesIsSkippedWithoutALookup(): void
    {
        $this->enable();
        $tuuid = Tuuid::generate();

        $en = new StiToy();
        $en->setMaterial('Wood')->setTuuid($tuuid)->setLocale('en_US');
        $de = new StiToy();
        $de->setMaterial('Holz')->setTuuid($tuuid)->setLocale('de_DE');
        $this->persistAll($en, $de);

        $en->setMaterial('Oak');

        $this->counter()->reset();
        $this->entityManager()->flush();

        self::assertSame(1, $this->counter()->count());

        $deId = $de->getId();
        self::assertNotNull($deId);
        $this->entityManager()->clear();
        self::assertSame('Holz', $this->find(StiToy::class, $deId)->getMaterial());
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Registers one more listener instance on the test EntityManager -- the
     * container's own instance runs with the flag off.
     *
     * @template T of AbstractLogger|null
     *
     * @param T $logger
     *
     * @return T
     */
    private function enable(AbstractLogger|null $logger = null, bool $enabled = true): AbstractLogger|null
    {
        $listener = new SharedValuePropagationListener($this->synchronizer(), $enabled, $logger);

        $this->entityManager()->getEventManager()->addEventListener(Events::onFlush, $listener);

        return $logger;
    }

    private function synchronizer(): SharedValueSynchronizer
    {
        $synchronizer = self::getContainer()->get('test.shared_value_synchronizer');
        self::assertInstanceOf(SharedValueSynchronizer::class, $synchronizer);

        return $synchronizer;
    }

    private function counter(): QueryCounter
    {
        $counter = self::getContainer()->get(QueryCounter::class);
        self::assertInstanceOf(QueryCounter::class, $counter);

        return $counter;
    }

    private function persistAll(object ...$entities): void
    {
        foreach ($entities as $entity) {
            $this->entityManager()->persist($entity);
        }

        $this->entityManager()->flush();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function find(string $class, int $id): object
    {
        $entity = new LocaleVariantFinder($this->entityManager())
            ->withoutLocaleFilter(fn (): object|null => $this->entityManager()->find($class, $id));

        self::assertInstanceOf($class, $entity);

        return $entity;
    }

    /**
     * Persists one Scalar per locale, then reloads them MANAGED from a cleared
     * EntityManager -- the state an application holds a just-edited row in.
     *
     * @param array<string, string> $sharedByLocale locale => shared value
     *
     * @return array<string, Scalar> locale => managed row
     */
    private function seedScalars(Tuuid $tuuid, array $sharedByLocale): array
    {
        foreach ($sharedByLocale as $locale => $shared) {
            $this->entityManager()->persist(
                new Scalar()->setTuuid($tuuid)->setLocale($locale)->setTitle('Title '.$locale)->setShared($shared),
            );
        }

        $this->entityManager()->flush();

        return $this->reloadScalars($tuuid);
    }

    /**
     * @return array<string, Scalar> locale => row, from a cleared EntityManager
     */
    private function reloadScalars(Tuuid $tuuid): array
    {
        $this->entityManager()->clear();

        return $this->reloadManaged($tuuid);
    }

    /**
     * @return array<string, Scalar> locale => row, through the current identity map
     */
    private function reloadManaged(Tuuid $tuuid): array
    {
        $rows = [];

        foreach (new LocaleVariantFinder($this->entityManager())->findAllLocaleVariants(Scalar::class, $tuuid) as $locale => $row) {
            self::assertInstanceOf(Scalar::class, $row);
            $rows[$locale] = $row;
        }

        return $rows;
    }

    /**
     * @return AbstractLogger&object{records: list<array{level: mixed, message: string, context: array<mixed>}>}
     */
    private function spyLogger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
            public array $records = [];

            /**
             * @param array<mixed> $context
             */
            public function log(mixed $level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };
    }
}

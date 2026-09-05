<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\ValueObject\SharedDrift;

/**
 * Read-only drift report over a whole translatable table: which sibling rows
 * carry a #[SharedAmongstTranslations] value that differs from the row the
 * back-fill treats as canonical.
 *
 * This is the read side of `tmi:translation:sync-shared --check` as a service,
 * for an application that wants to watch a production database for drift on
 * its own schedule (a daily scheduler task that logs and mails) without
 * parsing console output. Same streaming ({@see LocaleVariantFinder::streamGroupedByTuuid()}),
 * same source rule ({@see pickSource()}), same discovery and comparison
 * ({@see SharedValueSynchronizer::compare()}) as the command — the two cannot
 * disagree about what has drifted.
 *
 * Memory stays bounded by the locale count, not the table: every group is
 * detached once its drift has been yielded. Consumers must therefore not
 * `EntityManager::clear()` mid-iteration (see the streaming method's docblock).
 */
final readonly class SharedDriftScanner
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LocaleVariantFinder $finder,
        private SharedValueSynchronizer $synchronizer,
        private string $defaultLocale,
    ) {
    }

    /**
     * One {@see SharedDrift} per (sibling row, property path) whose value
     * differs from the group's source row; readonly properties included,
     * flagged. Nothing is written.
     *
     * @param class-string $class a translatable entity class — the root of an
     *                            inheritance hierarchy covers every concrete subclass
     *
     * @return \Generator<int, SharedDrift>
     */
    public function scan(string $class): \Generator
    {
        foreach ($this->finder->streamGroupedByTuuid($class) as $variants) {
            $source = $this->pickSource($variants);

            foreach ($variants as $sibling) {
                if ($sibling === $source) {
                    continue;
                }

                $report = $this->synchronizer->compare($source, $sibling);

                foreach ($report->changed() as $path) {
                    yield $this->drift($source, $sibling, $path, false);
                }

                foreach ($report->readonlyDrift() as $path) {
                    yield $this->drift($source, $sibling, $path, true);
                }
            }

            foreach ($variants as $variant) {
                $this->entityManager->detach($variant);
            }
        }
    }

    /**
     * The row a back-fill copies FROM: the default-locale variant when the
     * group has one, otherwise the first row of the group. This is the rule
     * `tmi:translation:sync-shared` has always applied — and the reason its
     * write mode is unsafe on data edited in another locale, which is what
     * `--tuuid --source-locale` and the flush-time propagation exist for.
     *
     * @param non-empty-list<TranslatableInterface> $variants
     */
    public function pickSource(array $variants): TranslatableInterface
    {
        foreach ($variants as $variant) {
            if ($variant->getLocale() === $this->defaultLocale) {
                return $variant;
            }
        }

        return $variants[0];
    }

    private function drift(TranslatableInterface $source, TranslatableInterface $sibling, string $path, bool $readonly): SharedDrift
    {
        return new SharedDrift(
            $this->entityManager->getClassMetadata($sibling::class)->getName(),
            (string) $sibling->getTuuid(),
            $path,
            $source->getLocale()  ?? '',
            $sibling->getLocale() ?? '',
            $readonly,
        );
    }
}

<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

/**
 * Discovers all mapped entity classes that implement {@see TranslatableInterface}.
 *
 * Shared by the diagnostic and maintenance console commands so they agree on
 * exactly which tables count as translatable.
 *
 * One entry per inheritance hierarchy, not per class: only the root of a
 * SINGLE_TABLE or JOINED hierarchy is returned, since querying it is already
 * polymorphic (every concrete subclass's rows come back in that one query).
 * Callers that need a concrete subclass's own fields — e.g. a
 * #[SharedAmongstTranslations] property declared only on a subclass — resolve
 * them per hydrated instance instead of relying on this list to name every
 * subclass.
 */
final readonly class TranslatableEntityLocator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<class-string<TranslatableInterface>>
     */
    public function locate(): array
    {
        $classes = [];

        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata->isMappedSuperclass) {
                continue;
            }

            // Inheritance hierarchies (STI and JOINED alike) map every concrete
            // subclass to its own ClassMetadata, but only the root owns the query
            // surface: querying the root is already polymorphic and returns every
            // concrete row. Without this check a hierarchy of N subclasses would be
            // walked N+1 times by every consumer of this list (doctor, sync-shared),
            // once per subclass on top of the already-complete root pass.
            if ($metadata->rootEntityName !== $metadata->name) {
                continue;
            }

            if (!$metadata->getReflectionClass()->implementsInterface(TranslatableInterface::class)) {
                continue;
            }

            /** @var class-string<TranslatableInterface> $name */
            $name      = $metadata->getName();
            $classes[] = $name;
        }

        sort($classes);

        return $classes;
    }
}

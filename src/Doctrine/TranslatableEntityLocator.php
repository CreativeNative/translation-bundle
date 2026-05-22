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

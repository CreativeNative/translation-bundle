<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\UniqueConstraintValidator;
use Tmi\TranslationBundle\Exception\ValidationException;

/**
 * Refuses a translatable entity whose unique constraints ignore the locale column, the
 * moment Doctrine loads its mapping.
 *
 * This gate used to be an optional cache warmer, which Symfony runs from `cache:warmup`
 * and `cache:clear` -- but not on the lazy container rebuild `Kernel::initializeContainer()`
 * performs when the cache is simply absent (a fresh deploy, a wiped cache directory), so
 * the check silently never ran on exactly that path. A `loadClassMetadata` listener has
 * no such gap: whatever loads the mapping first -- the first request, a console command,
 * the schema tools, a test kernel's boot -- runs it, and a mapping Doctrine has cached
 * already passed it once.
 *
 * Priority -10 so it runs AFTER {@see TranslatableIndexListener}: the `(tuuid, locale)`
 * constraint that listener may inject carries the locale column and is skipped, so the
 * order does not change the verdict, but the metadata seen here is the final one.
 */
#[AsDoctrineListener(event: Events::loadClassMetadata, priority: -10)]
final readonly class UniqueConstraintListener
{
    public function __construct(
        private UniqueConstraintValidator $validator = new UniqueConstraintValidator(),
    ) {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $metadata = $args->getClassMetadata();

        // A mapped superclass has no table of its own -- its $table would be a guessed
        // default, not a real column set to validate.
        if ($metadata->isMappedSuperclass) {
            return;
        }

        if (!is_a($metadata->getName(), TranslatableInterface::class, true)) {
            return;
        }

        $errors = $this->validator->validate($metadata);

        if ([] !== $errors) {
            throw ValidationException::fromMessages('Unique constraint validation', $errors);
        }
    }
}

<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Exception;

/**
 * `#[SharedAmongstTranslations]` on an association whose target is itself translatable.
 *
 * Sharing means "the identical instance on every locale variant", and for a
 * translatable target that would leave the relation's ownership ambiguous across
 * variants -- which locale's row does the shared child belong to? Every handler that
 * can see such an association refuses it with this exception, in the same words.
 * `\RuntimeException` is the base so a caller that catches that (contract § 13) keeps
 * working.
 */
final class SharedAssociationException extends \RuntimeException
{
    /**
     * @param string $associationKind the mapping as the handler names it, e.g. "bidirectional ManyToOne"
     */
    public static function forAssociation(string $associationKind, string $class, string $property): self
    {
        return new self(\sprintf(
            '%s::$%s is a %s association to a translatable entity and cannot be shared amongst translations. '
            .'Solution: remove #[SharedAmongstTranslations] from the property, or share the related entity\'s own columns instead.',
            $class,
            $property,
            $associationKind,
        ));
    }
}

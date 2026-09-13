<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Exception;

/**
 * Thrown by `tmi:translation:adopt-root` when a
 * {@see \Tmi\TranslationBundle\Doctrine\Root\RootAdopterInterface::createRootFor()}
 * result cannot be adopted onto a group. Raised BEFORE the root is persisted, so the
 * batch it belongs to is never flushed.
 */
final class RootAdoptionException extends \RuntimeException
{
    public static function forMintedRoot(string $rootClass, string $tuuid): self
    {
        return new self(sprintf(
            'The root adopter returned a %s that already carries a tuuid for the group %s. createRootFor() must return a root WITHOUT an identity -- the command adopts the group\'s tuuid onto it, so every translation row keeps the identity it already has. '
            .'Solution: do not call mintTuuid()/adoptTuuid() inside createRootFor().',
            $rootClass,
            $tuuid,
        ));
    }

    public static function forWrongRootClass(string $expected, string $actual, string $tuuid): self
    {
        return new self(sprintf(
            'The root adopter returned a %s for the group %s, but its rootClassFor() says the rows imply %s. The two answers must agree, otherwise a later --check would classify the group as drift. '
            .'Solution: derive both from the same fact (the rows\' discriminator).',
            $actual,
            $tuuid,
            $expected,
        ));
    }
}

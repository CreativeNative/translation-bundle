<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Exception\SharedAssociationException;

#[CoversClass(SharedAssociationException::class)]
final class SharedAssociationExceptionTest extends TestCase
{
    /** A \RuntimeException, so the documented `catch (\RuntimeException)` (contract § 13) keeps working. */
    public function testNamesTheAssociationAndTheWayOut(): void
    {
        $exception = SharedAssociationException::forAssociation('bidirectional ManyToOne', 'App\Entity\Comment', 'article');

        self::assertSame(
            'App\Entity\Comment::$article is a bidirectional ManyToOne association to a translatable entity and cannot be shared amongst translations. '
            .'Solution: remove #[SharedAmongstTranslations] from the property, or share the related entity\'s own columns instead.',
            $exception->getMessage(),
        );
    }
}

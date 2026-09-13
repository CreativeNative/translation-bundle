<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Exception\RootAdoptionException;

#[CoversClass(RootAdoptionException::class)]
final class RootAdoptionExceptionTest extends TestCase
{
    public function testForMintedRootIsARuntimeExceptionNamingTheClassTheGroupAndTheSolution(): void
    {
        $exception = RootAdoptionException::forMintedRoot('App\Entity\Listing', 'tuuid-1');

        $parents = class_parents($exception);
        self::assertNotEmpty($parents);
        self::assertContains(\RuntimeException::class, $parents);
        self::assertStringContainsString('returned a App\Entity\Listing that already carries a tuuid for the group tuuid-1', $exception->getMessage());
        self::assertStringContainsString('Solution: do not call mintTuuid()/adoptTuuid() inside createRootFor()', $exception->getMessage());
    }

    public function testForWrongRootClassNamesBothClassesAndTheGroup(): void
    {
        $message = RootAdoptionException::forWrongRootClass('App\Entity\VacationRentalListing', 'App\Entity\RealEstateListing', 'tuuid-1')->getMessage();

        self::assertStringContainsString('returned a App\Entity\RealEstateListing for the group tuuid-1', $message);
        self::assertStringContainsString('rootClassFor() says the rows imply App\Entity\VacationRentalListing', $message);
        self::assertStringContainsString('Solution:', $message);
    }
}

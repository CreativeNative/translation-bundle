<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\ValueObject\SharedDrift;

#[CoversClass(SharedDrift::class)]
final class SharedDriftTest extends TestCase
{
    public function testExposesEveryLocationOfTheDrift(): void
    {
        $drift = new SharedDrift(Scalar::class, 'tuuid-1', 'address.street', 'en_US', 'de_DE', true);

        self::assertSame(Scalar::class, $drift->entityClass());
        self::assertSame('tuuid-1', $drift->tuuid());
        self::assertSame('address.street', $drift->propertyPath());
        self::assertSame('en_US', $drift->sourceLocale());
        self::assertSame('de_DE', $drift->locale());
        self::assertTrue($drift->isReadonly());
    }
}

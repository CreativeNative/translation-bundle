<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\ValueObject\SharedValueChange;

#[CoversClass(SharedValueChange::class)]
final class SharedValueChangeTest extends TestCase
{
    public function testCarriesPathKindAndBothValuesUntouched(): void
    {
        $old    = new \stdClass();
        $new    = new \stdClass();
        $change = new SharedValueChange('address.street', true, $old, $new);

        self::assertSame('address.street', $change->path);
        self::assertTrue($change->association);
        self::assertSame($old, $change->old, 'values are carried as they are, never cloned or rendered');
        self::assertSame($new, $change->new);
    }
}

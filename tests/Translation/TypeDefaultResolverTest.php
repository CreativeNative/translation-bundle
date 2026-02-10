<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Translation;

use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Translation\TypeDefaultResolver;

enum TestStatus
{
    case Active;
    case Inactive;
}

enum TestStringStatus: string
{
    case Draft     = 'draft';
    case Published = 'published';
}

final class TypeDefaultResolverTest extends TestCase
{
    private TypeDefaultResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new TypeDefaultResolver();
    }

    public function testResolveUntypedPropertyReturnsNull(): void
    {
        $property = new \ReflectionProperty(new class {
            /** @phpstan-ignore missingType.property */
            public $untyped;
        }, 'untyped');

        self::assertNull($this->resolver->resolve($property));
    }

    public function testResolveNullableStringReturnsNull(): void
    {
        $property = new \ReflectionProperty(new class {
            public string|null $name = null;
        }, 'name');

        self::assertNull($this->resolver->resolve($property));
    }

    public function testResolveNullableIntReturnsNull(): void
    {
        $property = new \ReflectionProperty(new class {
            public int|null $count = null;
        }, 'count');

        self::assertNull($this->resolver->resolve($property));
    }

    public function testResolveNonNullableStringReturnsEmptyString(): void
    {
        $property = new \ReflectionProperty(new class {
            public string $title = '';
        }, 'title');

        self::assertSame('', $this->resolver->resolve($property));
    }

    public function testResolveNonNullableStringIgnoresDeclaredDefault(): void
    {
        $property = new \ReflectionProperty(new class {
            public string $status = 'draft';
        }, 'status');

        self::assertSame('', $this->resolver->resolve($property));
    }

    public function testResolveNonNullableIntReturnsZero(): void
    {
        $property = new \ReflectionProperty(new class {
            public int $count = 0;
        }, 'count');

        self::assertSame(0, $this->resolver->resolve($property));
    }

    public function testResolveNonNullableFloatReturnsZeroFloat(): void
    {
        $property = new \ReflectionProperty(new class {
            public float $price = 0.0;
        }, 'price');

        self::assertSame(0.0, $this->resolver->resolve($property));
    }

    public function testResolveNonNullableBoolReturnsFalse(): void
    {
        $property = new \ReflectionProperty(new class {
            public bool $active = false;
        }, 'active');

        self::assertFalse($this->resolver->resolve($property));
    }

    public function testResolveNonNullableArrayReturnsEmptyArray(): void
    {
        $property = new \ReflectionProperty(new class {
            /** @phpstan-ignore missingType.iterableValue */
            public array $items = [];
        }, 'items');

        self::assertSame([], $this->resolver->resolve($property));
    }

    public function testResolveNullableShorthandReturnsNull(): void
    {
        $property = new \ReflectionProperty(new class {
            public string|null $name = null;
        }, 'name');

        self::assertNull($this->resolver->resolve($property));
    }

    public function testResolveUnionTypeUsesFirstNonNullTypeDefault(): void
    {
        $property = new \ReflectionProperty(new class {
            public string|int $value = '';
        }, 'value');

        self::assertSame('', $this->resolver->resolve($property));
    }

    public function testResolveUnionTypeIntStringUsesFirstReflectionType(): void
    {
        // PHP normalizes union types alphabetically: int|string -> [string, int]
        // So the first type from getTypes() is 'string', giving ''
        $property = new \ReflectionProperty(new class {
            public int|string $value = 0;
        }, 'value');

        self::assertSame('', $this->resolver->resolve($property));
    }

    public function testResolveNullableUnionReturnsNull(): void
    {
        $property = new \ReflectionProperty(new class {
            public string|int|null $value = null;
        }, 'value');

        self::assertNull($this->resolver->resolve($property));
    }

    public function testResolveNonNullableEnumThrowsLogicException(): void
    {
        $property = new \ReflectionProperty(new class {
            public TestStatus $status;
        }, 'status');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'is a non-nullable enum and cannot have a type-safe default. Make it nullable or use #[SharedAmongstTranslations].',
        );

        $this->resolver->resolve($property);
    }

    public function testResolveNonNullableBackedEnumThrowsLogicException(): void
    {
        $property = new \ReflectionProperty(new class {
            public TestStringStatus $status;
        }, 'status');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'is a non-nullable enum and cannot have a type-safe default. Make it nullable or use #[SharedAmongstTranslations].',
        );

        $this->resolver->resolve($property);
    }

    public function testResolveNullableEnumReturnsNull(): void
    {
        $property = new \ReflectionProperty(new class {
            public TestStatus|null $status = null;
        }, 'status');

        self::assertNull($this->resolver->resolve($property));
    }

    public function testResolveNonNullableObjectThrowsLogicException(): void
    {
        $property = new \ReflectionProperty(new class {
            public \DateTimeImmutable $createdAt;
        }, 'createdAt');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'is a non-nullable object and cannot have a type-safe default. Make it nullable, remove #[EmptyOnTranslate], or use #[SharedAmongstTranslations].',
        );

        $this->resolver->resolve($property);
    }

    public function testResolveNonNullableDateTimeThrowsLogicException(): void
    {
        $property = new \ReflectionProperty(new class {
            public \DateTime $updatedAt;
        }, 'updatedAt');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'is a non-nullable object and cannot have a type-safe default. Make it nullable, remove #[EmptyOnTranslate], or use #[SharedAmongstTranslations].',
        );

        $this->resolver->resolve($property);
    }

    public function testResolveNullableObjectReturnsNull(): void
    {
        $property = new \ReflectionProperty(new class {
            public \DateTimeImmutable|null $createdAt = null;
        }, 'createdAt');

        self::assertNull($this->resolver->resolve($property));
    }

    public function testResolveNonNullableCustomObjectThrowsLogicException(): void
    {
        $property = new \ReflectionProperty(new class {
            public \stdClass $data;
        }, 'data');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'is a non-nullable object and cannot have a type-safe default. Make it nullable, remove #[EmptyOnTranslate], or use #[SharedAmongstTranslations].',
        );

        $this->resolver->resolve($property);
    }

    public function testResolveIntersectionTypeReturnsNull(): void
    {
        $property = new \ReflectionProperty(new class {
            public \Countable&\Iterator $collection;
        }, 'collection');

        self::assertNull($this->resolver->resolve($property));
    }

    public function testResolveEnumExceptionContainsPropertyAndClassName(): void
    {
        $property = new \ReflectionProperty(new class {
            public TestStatus $status;
        }, 'status');

        try {
            $this->resolver->resolve($property);
            self::fail('Expected LogicException');
        } catch (\LogicException $e) {
            self::assertStringContainsString('$status', $e->getMessage());
            self::assertStringContainsString('#[SharedAmongstTranslations]', $e->getMessage());
        }
    }

    public function testResolveObjectExceptionContainsPropertyAndClassName(): void
    {
        $property = new \ReflectionProperty(new class {
            public \DateTimeImmutable $createdAt;
        }, 'createdAt');

        try {
            $this->resolver->resolve($property);
            self::fail('Expected LogicException');
        } catch (\LogicException $e) {
            self::assertStringContainsString('$createdAt', $e->getMessage());
            self::assertStringContainsString('#[EmptyOnTranslate]', $e->getMessage());
            self::assertStringContainsString('#[SharedAmongstTranslations]', $e->getMessage());
        }
    }

    public function testResolveNonNullableBoolIgnoresDeclaredDefault(): void
    {
        $property = new \ReflectionProperty(new class {
            public bool $active = true;
        }, 'active');

        self::assertFalse($this->resolver->resolve($property));
    }

    public function testResolveNonNullableIntIgnoresDeclaredDefault(): void
    {
        $property = new \ReflectionProperty(new class {
            public int $priority = 42;
        }, 'priority');

        self::assertSame(0, $this->resolver->resolve($property));
    }

    public function testResolveNonNullableIterableReturnsNull(): void
    {
        // iterable is a built-in type not in SCALAR_DEFAULTS
        $property = new \ReflectionProperty(new class {
            /** @phpstan-ignore missingType.iterableValue */
            public iterable $items = [];
        }, 'items');

        self::assertNull($this->resolver->resolve($property));
    }
}

<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Doctrine\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\FieldMapping;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Doctrine\EventListener\UniqueConstraintListener;
use Tmi\TranslationBundle\Exception\ValidationException;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\NonTranslatableManyToOneBidirectionalChild;

/**
 * Negative proof against 5.1: the unique-constraint gate was an optional cache warmer and
 * never ran on the lazy container rebuild; there was no listener to hand this metadata to.
 */
#[CoversClass(UniqueConstraintListener::class)]
final class UniqueConstraintListenerTest extends TestCase
{
    public function testABadTranslatableMappingFailsTheMetadataLoad(): void
    {
        $metadata = $this->metadata(Scalar::class);
        $this->addUniqueField($metadata, 'slug');
        $metadata->table['uniqueConstraints'] = ['uniq_code' => ['fields' => ['code']]];

        try {
            new UniqueConstraintListener()->loadClassMetadata($this->args($metadata));
            self::fail('expected a ValidationException');
        } catch (ValidationException $exception) {
            self::assertStringStartsWith("TMI Translation Bundle: Unique constraint validation failed with 2 error(s):\n\n", $exception->getMessage());
            self::assertStringContainsString('- Entity "Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar": field "slug" has a single-column unique constraint.', $exception->getMessage());
            self::assertStringContainsString('- Entity "Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar": unique constraint "uniq_code" on fields ["code"] does not include the locale column.', $exception->getMessage());
            self::assertCount(2, $exception->getErrors());
        }
    }

    public function testACleanTranslatableMappingPasses(): void
    {
        $metadata                             = $this->metadata(Scalar::class);
        $metadata->table['uniqueConstraints'] = ['uniq_slug_locale' => ['fields' => ['slug', 'locale']]];

        new UniqueConstraintListener()->loadClassMetadata($this->args($metadata));

        self::assertSame(['uniq_slug_locale' => ['fields' => ['slug', 'locale']]], $metadata->table['uniqueConstraints'], 'the listener validates and never rewrites the mapping');
    }

    public function testAMappedSuperclassIsSkipped(): void
    {
        $metadata                     = $this->metadata(Scalar::class);
        $metadata->isMappedSuperclass = true;
        $this->addUniqueField($metadata, 'slug');

        new UniqueConstraintListener()->loadClassMetadata($this->args($metadata));

        self::assertTrue($metadata->isMappedSuperclass);
    }

    public function testANonTranslatableEntityIsSkipped(): void
    {
        $metadata = $this->metadata(NonTranslatableManyToOneBidirectionalChild::class);
        $this->addUniqueField($metadata, 'email');

        new UniqueConstraintListener()->loadClassMetadata($this->args($metadata));

        self::assertTrue($metadata->fieldMappings['email']->unique, 'a non-translatable entity may be unique per row');
    }

    /**
     * @param class-string $className
     *
     * @return ClassMetadata<object>
     */
    private function metadata(string $className): ClassMetadata
    {
        /** @var ClassMetadata<object> $metadata */
        $metadata        = new ClassMetadata($className);
        $metadata->table = ['name' => 'test_table'];

        return $metadata;
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function addUniqueField(ClassMetadata $metadata, string $name): void
    {
        $field                          = new FieldMapping(type: 'string', fieldName: $name, columnName: $name);
        $field->unique                  = true;
        $metadata->fieldMappings[$name] = $field;
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function args(ClassMetadata $metadata): LoadClassMetadataEventArgs
    {
        return new LoadClassMetadataEventArgs($metadata, self::createStub(EntityManagerInterface::class));
    }
}

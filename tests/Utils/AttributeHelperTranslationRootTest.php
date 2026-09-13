<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Utils;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Attribute\TranslationRoot;
use Tmi\TranslationBundle\Doctrine\Model\TranslationRootInterface;
use Tmi\TranslationBundle\Exception\AttributeConflictException;
use Tmi\TranslationBundle\Exception\TranslationRootContractException;
use Tmi\TranslationBundle\Exception\ValidationException;
use Tmi\TranslationBundle\Fixtures\Entity\Root\Article;
use Tmi\TranslationBundle\Fixtures\Entity\Root\Estate;
use Tmi\TranslationBundle\Fixtures\Entity\Root\Listing;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Fixtures\Entity\Translatable\TranslatableManyToOneUnidirectional;
use Tmi\TranslationBundle\Fixtures\Validation\Root\Ambiguous\AmbiguousRootEntity;
use Tmi\TranslationBundle\Fixtures\Validation\Root\EmptyOnTranslateRoot\EmptyOnTranslateRootEntity;
use Tmi\TranslationBundle\Fixtures\Validation\Root\IdRoot\IdRootEntity;
use Tmi\TranslationBundle\Fixtures\Validation\Root\MarkerWithoutRoot\MarkerWithoutRootEntity;
use Tmi\TranslationBundle\Fixtures\Validation\Root\TranslatableRootType\TranslatableRootTypeEntity;
use Tmi\TranslationBundle\Fixtures\Validation\Root\UniqueJoinColumn\UniqueJoinColumnRootEntity;
use Tmi\TranslationBundle\Fixtures\Validation\Root\Valid\Phase1Entity;
use Tmi\TranslationBundle\Fixtures\Validation\Root\Valid\Phase2InterfaceEntity;
use Tmi\TranslationBundle\Utils\AttributeHelper;

/**
 * The structural root-reference test and the per-property half of the root contract (5.1).
 */
#[CoversClass(AttributeHelper::class)]
#[CoversClass(TranslationRoot::class)]
final class AttributeHelperTranslationRootTest extends TestCase
{
    private AttributeHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->helper = new AttributeHelper();
    }

    // ------------------------------------------------------------------
    // isTranslationRootReference(): the structural test
    // ------------------------------------------------------------------

    public function testAManyToOneTypedToARootClassIsARootReferenceWithOrWithoutTheMarker(): void
    {
        self::assertTrue($this->helper->isTranslationRootReference(new \ReflectionProperty(Estate::class, 'listing')), 'with #[TranslationRoot]');
        self::assertTrue($this->helper->isTranslationRootReference(new \ReflectionProperty(Article::class, 'root')), 'without the marker');
    }

    public function testAManyToOneTypedToTheBareInterfaceIsARootReference(): void
    {
        // class_exists() is false for an interface name -- the test must not be gated behind it.
        $property = new \ReflectionProperty(Phase2InterfaceEntity::class, 'root');

        self::assertTrue($this->helper->isTranslationRootReference($property));
        self::assertSame(TranslationRootInterface::class, $this->helper->translationRootType($property));
    }

    public function testAManyToOneToANonRootTargetIsNotARootReference(): void
    {
        self::assertFalse($this->helper->isTranslationRootReference(new \ReflectionProperty(TranslatableManyToOneUnidirectional::class, 'sharedToNonTranslatable')));
        self::assertFalse($this->helper->isTranslationRootReference(new \ReflectionProperty(TranslatableManyToOneUnidirectional::class, 'sharedToTranslatable')));
    }

    public function testAOneToOneTypedToARootIsNotARootReference(): void
    {
        $property = new \ReflectionProperty(MarkerWithoutRootEntity::class, 'oneToOne');

        self::assertFalse($this->helper->isTranslationRootReference($property), 'OneToOne cardinality is never right for a root');
        self::assertNotNull($this->helper->translationRootType($property), 'the TYPE half of the test still holds');
    }

    public function testCollectionsBuiltinsAndUntypedPropertiesAreNotRootReferences(): void
    {
        $holder = new class {
            /** @var Collection<int, Listing> */
            #[ORM\OneToMany(targetEntity: Listing::class, mappedBy: 'nothing')]
            public Collection $roots;

            #[ORM\ManyToOne(targetEntity: Listing::class)]
            public string $builtin = '';

            #[ORM\ManyToOne(targetEntity: Listing::class)]
            public $untyped; // @phpstan-ignore missingType.property

            #[ORM\ManyToOne(targetEntity: Listing::class)]
            public Listing|Scalar|null $union = null;

            public function __construct()
            {
                $this->roots = new ArrayCollection();
            }
        };

        foreach (['roots', 'builtin', 'untyped', 'union'] as $name) {
            $property = new \ReflectionProperty($holder, $name);

            self::assertFalse($this->helper->isTranslationRootReference($property), $name);
            self::assertNull($this->helper->translationRootType($property), $name);
        }
    }

    public function testTheStructuralTestIsFalseForEveryPropertyOfAPre51Fixture(): void
    {
        // R11's proof: a class that predates 5.1 cannot reference a 5.1 interface.
        foreach (new \ReflectionClass(Scalar::class)->getProperties() as $property) {
            self::assertFalse($this->helper->isTranslationRootReference($property), $property->getName());
        }
    }

    public function testTheStructuralTestIsMemoizedPerProperty(): void
    {
        $property = new \ReflectionProperty(Estate::class, 'listing');

        self::assertTrue($this->helper->isTranslationRootReference($property));

        $cache = new \ReflectionProperty(AttributeHelper::class, 'rootReferenceCache');
        /** @var array<string, bool> $cached */
        $cached = $cache->getValue($this->helper);

        self::assertSame([Estate::class.'::listing' => true], $cached);
        self::assertTrue($this->helper->isTranslationRootReference($property));
    }

    // ------------------------------------------------------------------
    // isEffectivelyShared() truth table, hasTranslationRootMarker()
    // ------------------------------------------------------------------

    public function testIsEffectivelySharedIsTheDisjunctionOfAttributeAndRootReference(): void
    {
        $sharedOnly = new \ReflectionProperty(Scalar::class, 'shared');
        $rootOnly   = new \ReflectionProperty(Article::class, 'root');
        $both       = new \ReflectionProperty(Phase1Entity::class, 'root');
        $neither    = new \ReflectionProperty(Scalar::class, 'title');

        self::assertTrue($this->helper->isEffectivelyShared($sharedOnly));
        self::assertTrue($this->helper->isEffectivelyShared($rootOnly));
        self::assertTrue($this->helper->isEffectivelyShared($both));
        self::assertFalse($this->helper->isEffectivelyShared($neither));

        // The literal method stays literal.
        self::assertFalse($this->helper->isSharedAmongstTranslations($rootOnly));
        self::assertTrue($this->helper->isSharedAmongstTranslations($both));
    }

    public function testHasTranslationRootMarkerReadsTheAttributeOnly(): void
    {
        self::assertTrue($this->helper->hasTranslationRootMarker(new \ReflectionProperty(Estate::class, 'listing')));
        self::assertFalse($this->helper->hasTranslationRootMarker(new \ReflectionProperty(Article::class, 'root')));
        self::assertTrue($this->helper->hasTranslationRootMarker(new \ReflectionProperty(MarkerWithoutRootEntity::class, 'title')));
    }

    // ------------------------------------------------------------------
    // validateProperty(): the per-property half of the contract
    // ------------------------------------------------------------------

    public function testAValidRootReferencePassesValidationIncludingARedundantSharedAttribute(): void
    {
        $this->helper->validateProperty(new \ReflectionProperty(Estate::class, 'listing'));
        $this->helper->validateProperty(new \ReflectionProperty(Article::class, 'root'));
        $this->helper->validateProperty(new \ReflectionProperty(Phase1Entity::class, 'root'));

        $this->addToAssertionCount(1);
    }

    public function testAMarkerOnANonRootPropertyIsRefused(): void
    {
        $errors = $this->errorsOf(new \ReflectionProperty(MarkerWithoutRootEntity::class, 'title'));

        self::assertCount(1, $errors);
        self::assertInstanceOf(TranslationRootContractException::class, $errors[0]);
        self::assertStringContainsString('carries #[TranslationRoot] but is not a root reference', $errors[0]->getMessage());
        self::assertStringContainsString('Solution:', $errors[0]->getMessage());
    }

    public function testAMarkerOnAOneToOneTypedToARootIsRefused(): void
    {
        $errors = $this->errorsOf(new \ReflectionProperty(MarkerWithoutRootEntity::class, 'oneToOne'));

        self::assertCount(1, $errors);
        self::assertInstanceOf(TranslationRootContractException::class, $errors[0]);
    }

    public function testARootTypeThatIsAlsoTranslatableIsRefused(): void
    {
        $errors = $this->errorsOf(new \ReflectionProperty(TranslatableRootTypeEntity::class, 'root'));

        self::assertCount(1, $errors);
        self::assertStringContainsString('implements TranslationRootInterface AND TranslatableInterface', $errors[0]->getMessage());
    }

    public function testAnIdRootReferenceIsRefused(): void
    {
        $errors = $this->errorsOf(new \ReflectionProperty(IdRootEntity::class, 'root'));

        self::assertCount(1, $errors);
        self::assertStringContainsString('#[ORM\Id]', $errors[0]->getMessage());
    }

    public function testEmptyOnTranslateOnARootReferenceIsTheRootErrorNotTheSharedEmptyConflict(): void
    {
        $errors = $this->errorsOf(new \ReflectionProperty(EmptyOnTranslateRootEntity::class, 'root'));

        self::assertCount(1, $errors);
        self::assertInstanceOf(TranslationRootContractException::class, $errors[0]);
        self::assertStringContainsString('#[EmptyOnTranslate]', $errors[0]->getMessage());
    }

    public function testAUniqueJoinColumnOnARootReferenceIsRefused(): void
    {
        $errors = $this->errorsOf(new \ReflectionProperty(UniqueJoinColumnRootEntity::class, 'root'));

        self::assertCount(1, $errors);
        self::assertStringContainsString('#[ORM\JoinColumn] is unique', $errors[0]->getMessage());
    }

    public function testEveryRootErrorOfOnePropertyIsCollected(): void
    {
        $holder = new class {
            #[ORM\Id]
            #[\Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate]
            #[ORM\ManyToOne(targetEntity: Listing::class)]
            #[ORM\JoinColumn(unique: true)]
            public Listing|null $root = null;
        };

        $errors = $this->errorsOf(new \ReflectionProperty($holder, 'root'));

        self::assertCount(3, $errors);
    }

    public function testTwoRootReferencesOnOneClassPassThePerPropertyCheckEach(): void
    {
        // Ambiguity is a per-CLASS fact; AttributeValidationPass reports it. Each
        // property on its own is a well-formed root reference.
        $this->helper->validateProperty(new \ReflectionProperty(AmbiguousRootEntity::class, 'first'));
        $this->helper->validateProperty(new \ReflectionProperty(AmbiguousRootEntity::class, 'second'));

        $this->addToAssertionCount(1);
    }

    public function testASharedAttributeOnANonRootPropertyStillConflictsWithEmptyOnTranslate(): void
    {
        $holder = new class {
            #[SharedAmongstTranslations]
            #[\Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate]
            public string|null $value = null;
        };

        $errors = $this->errorsOf(new \ReflectionProperty($holder, 'value'));

        self::assertCount(1, $errors);
        self::assertInstanceOf(AttributeConflictException::class, $errors[0]);
    }

    /**
     * @return list<\LogicException>
     */
    private function errorsOf(\ReflectionProperty $property): array
    {
        try {
            $this->helper->validateProperty($property);
        } catch (ValidationException $e) {
            return array_values($e->getErrors());
        }

        self::fail('Expected a ValidationException for '.$property->class.'::$'.$property->name);
    }
}

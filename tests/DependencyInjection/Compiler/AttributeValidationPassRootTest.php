<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\DependencyInjection\Compiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Tmi\TranslationBundle\DependencyInjection\Compiler\AttributeValidationPass;
use Tmi\TranslationBundle\Fixtures\Validation\Root\StiDedupe\StiLeafOne;
use Tmi\TranslationBundle\Fixtures\Validation\Root\StiDedupe\StiLeafTwo;
use Tmi\TranslationBundle\Fixtures\Validation\Root\Valid\Phase1Entity;
use Tmi\TranslationBundle\Fixtures\Validation\Root\Valid\Phase2Entity;
use Tmi\TranslationBundle\Fixtures\Validation\Root\Valid\Phase2InterfaceEntity;

/**
 * The translation root contract at compile time (5.1): one fixture directory per
 * row of the contract table, the nullable/non-nullable switch, and the SINGLE_TABLE
 * dedupe.
 */
#[CoversClass(AttributeValidationPass::class)]
final class AttributeValidationPassRootTest extends TestCase
{
    public function testValidPhase1AndPhase2DeclarationsPassAndAreExposedAsRootClasses(): void
    {
        $container = $this->containerScanning('Valid');

        new AttributeValidationPass()->process($container);

        self::assertSame(
            [Phase1Entity::class, Phase2Entity::class, Phase2InterfaceEntity::class],
            $container->getParameter(AttributeValidationPass::ROOT_CLASSES_PARAMETER),
        );
    }

    public function testAClassWithoutARootReferenceIsNotARootClass(): void
    {
        $container = $this->containerScanning('../Valid');

        new AttributeValidationPass()->process($container);

        self::assertSame([], $container->getParameter(AttributeValidationPass::ROOT_CLASSES_PARAMETER));
    }

    public function testTheRootClassesParameterIsEmptyWithoutDoctrine(): void
    {
        $container = new ContainerBuilder();

        new AttributeValidationPass()->process($container);

        self::assertSame([], $container->getParameter(AttributeValidationPass::ROOT_CLASSES_PARAMETER));
    }

    public function testTwoRootReferencesOnOneClassAreAmbiguous(): void
    {
        $this->expectRootError('Ambiguous', '2 properties are root references ($first, $second)');
    }

    public function testARootTypeThatIsAlsoTranslatableIsRefused(): void
    {
        $this->expectRootError('TranslatableRootType', 'implements TranslationRootInterface AND TranslatableInterface');
    }

    public function testAnIdRootReferenceIsRefused(): void
    {
        $this->expectRootError('IdRoot', 'carries #[ORM\Id]');
    }

    public function testEmptyOnTranslateOnARootReferenceIsRefused(): void
    {
        $this->expectRootError('EmptyOnTranslateRoot', 'carries #[EmptyOnTranslate]');
    }

    public function testAUniqueJoinColumnOnARootReferenceIsRefused(): void
    {
        $this->expectRootError('UniqueJoinColumn', '#[ORM\JoinColumn] is unique');
    }

    public function testAMarkerWithoutARootIsRefusedOnEveryProperty(): void
    {
        $message = $this->processExpectingFailure('MarkerWithoutRoot');

        self::assertStringContainsString('2 error(s)', $message);
        self::assertStringContainsString('::$title: the property carries #[TranslationRoot]', $message);
        self::assertStringContainsString('::$oneToOne: the property carries #[TranslationRoot]', $message);
    }

    /**
     * The phase-2 switch: a NON-nullable root reference turns the constructor rule on.
     * A constructor that merely accepts the root (nullable, defaulted) does not satisfy it.
     */
    public function testANonNullableRootReferenceRequiresARootConstructorParameter(): void
    {
        $message = $this->processExpectingFailure('MissingConstructor');

        self::assertStringContainsString('2 error(s)', $message);
        self::assertStringContainsString('MissingConstructorEntity: Translation root contract violated on', $message);
        self::assertStringContainsString('OptionalConstructorEntity: Translation root contract violated on', $message);
        self::assertStringContainsString('its root reference $root is non-nullable', $message);
        self::assertStringContainsString('no required, non-nullable parameter typed to TranslationRootInterface', $message);
    }

    /**
     * ONE AttributeHelper for the run: the property-level error declared on the abstract
     * ancestor is reported once, not once per leaf; the constructor rule -- keyed by
     * concrete class -- is reported per leaf; the abstract parent itself is never listed.
     */
    public function testStiDedupeReportsPropertyErrorsOncePerDeclaringClassAndConstructorErrorsPerLeaf(): void
    {
        $message = $this->processExpectingFailure('StiDedupe');

        self::assertStringContainsString('3 error(s)', $message);
        self::assertSame(1, substr_count($message, '#[ORM\JoinColumn] is unique'), 'the inherited property error is reported once');
        self::assertSame(2, substr_count($message, 'is non-nullable, so the class is past the migration phase'), 'the constructor rule once per concrete leaf');
        self::assertStringContainsString(StiLeafOne::class.': ', $message);
        self::assertStringContainsString(StiLeafTwo::class.': ', $message);
        self::assertStringNotContainsString('AbstractStiRows: ', $message);
    }

    private function expectRootError(string $fixtureDirectory, string $expectedFragment): void
    {
        $message = $this->processExpectingFailure($fixtureDirectory);

        self::assertStringContainsString('1 error(s)', $message);
        self::assertStringContainsString('Translation root contract violated', $message);
        self::assertStringContainsString($expectedFragment, $message);
        self::assertStringContainsString('Solution:', $message);
    }

    private function processExpectingFailure(string $fixtureDirectory): string
    {
        try {
            new AttributeValidationPass()->process($this->containerScanning($fixtureDirectory));
        } catch (\LogicException $e) {
            return $e->getMessage();
        }

        self::fail(sprintf('Expected the pass to refuse tests/Fixtures/Validation/Root/%s.', $fixtureDirectory));
    }

    private function containerScanning(string $fixtureDirectory): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('doctrine.orm.entity_manager', \stdClass::class);

        $driver = new Definition(\stdClass::class);
        $driver->addArgument([__DIR__.'/../../Fixtures/Validation/Root/'.$fixtureDirectory]);
        $container->setDefinition('doctrine.orm.default_attribute_metadata_driver', $driver);

        return $container;
    }
}

<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmi\TranslationBundle\Exception\TranslationRootContractException;

#[CoversClass(TranslationRootContractException::class)]
final class TranslationRootContractExceptionTest extends TestCase
{
    /**
     * Each case yields a closure rather than the built exception: a data provider runs
     * before coverage collection starts, so a factory called there would never be
     * credited to this test.
     *
     * @return iterable<string, array{\Closure(): TranslationRootContractException, list<string>}>
     */
    public static function factories(): iterable
    {
        yield 'ambiguous' => [static fn (): TranslationRootContractException => TranslationRootContractException::forAmbiguousRootProperty('App\Entity\Property', ['listing', 'owner']), ['App\Entity\Property', '2 properties are root references ($listing, $owner)']];
        yield 'translatable type' => [static fn (): TranslationRootContractException => TranslationRootContractException::forTranslatableRootType('App\Entity\Property', 'listing', 'App\Entity\Listing'), ['App\Entity\Property::$listing', 'App\Entity\Listing implements TranslationRootInterface AND TranslatableInterface']];
        yield 'id' => [static fn (): TranslationRootContractException => TranslationRootContractException::forIdRootProperty('App\Entity\Property', 'listing'), ['App\Entity\Property::$listing', '#[ORM\Id]', 'PrimaryKeyHandler']];
        yield 'empty on translate' => [static fn (): TranslationRootContractException => TranslationRootContractException::forEmptyOnTranslate('App\Entity\Property', 'listing'), ['App\Entity\Property::$listing', '#[EmptyOnTranslate]']];
        yield 'unique join column' => [static fn (): TranslationRootContractException => TranslationRootContractException::forUniqueJoinColumn('App\Entity\Property', 'listing'), ['App\Entity\Property::$listing', '#[ORM\JoinColumn] is unique', 'never OneToOne']];
        yield 'marker without root' => [static fn (): TranslationRootContractException => TranslationRootContractException::forMarkerWithoutRoot('App\Entity\Property', 'title'), ['App\Entity\Property::$title', '#[TranslationRoot] but is not a root reference']];
        yield 'missing constructor parameter' => [static fn (): TranslationRootContractException => TranslationRootContractException::forMissingRootConstructorParameter('App\Entity\Property', 'listing'), ['App\Entity\Property', '$listing is non-nullable', 'lazily mint']];
        yield 'missing adopter' => [static fn (): TranslationRootContractException => TranslationRootContractException::forMissingAdopter('App\Entity\Property'), ['App\Entity\Property declares a root reference but no service tagged', 'class: App\Entity\Property']];
        yield 'duplicate adopter' => [static fn (): TranslationRootContractException => TranslationRootContractException::forDuplicateAdopter('App\Entity\Property', ['a', 'b']), ['served by 2 root adopters (a, b)']];
        yield 'adopter without root' => [static fn (): TranslationRootContractException => TranslationRootContractException::forAdopterWithoutRoot('app.adopter', 'App\Entity\Property'), ['"app.adopter" is tagged', 'for App\Entity\Property']];
        yield 'tag without class' => [static fn (): TranslationRootContractException => TranslationRootContractException::forAdopterTagWithoutClass('app.adopter'), ['"app.adopter" is tagged', 'required `class` attribute']];
    }

    /**
     * @param \Closure(): TranslationRootContractException $factory
     * @param list<string>                                 $fragments
     */
    #[DataProvider('factories')]
    public function testEveryFactoryIsALogicExceptionNamingTheSubjectAndASolution(\Closure $factory, array $fragments): void
    {
        $exception = $factory();

        $parents = class_parents($exception);
        self::assertNotEmpty($parents);
        self::assertContains(\LogicException::class, $parents);
        self::assertStringStartsWith('Translation root contract violated', $exception->getMessage());
        self::assertStringContainsString('Solution:', $exception->getMessage());

        foreach ($fragments as $fragment) {
            self::assertStringContainsString($fragment, $exception->getMessage());
        }
    }
}

<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Exception;

/**
 * A translation row's root reference breaks the root contract -- one class for every
 * declaration error, because every check concerns the same thing (the reference to a
 * {@see \Tmi\TranslationBundle\Doctrine\Model\TranslationRootInterface}), not a pair of
 * conflicting attributes. Raised at container compile time by
 * `AttributeValidationPass` / `RootAdopterPass` and, for the per-property checks, by
 * `AttributeHelper::validateProperty()` at translate time; every message carries a
 * `Solution:` line.
 */
final class TranslationRootContractException extends \LogicException
{
    /**
     * @param list<string> $properties
     */
    public static function forAmbiguousRootProperty(string $class, array $properties): self
    {
        return new self(\sprintf(
            'Translation root contract violated on %s: %d properties are root references (%s), but a translation row copies its tuuid from exactly one root. '
            .'Solution: keep one ManyToOne typed to a TranslationRootInterface implementation and give the others a non-root type.',
            $class,
            \count($properties),
            implode(', ', array_map(static fn (string $p): string => '$'.$p, $properties)),
        ));
    }

    public static function forTranslatableRootType(string $class, string $property, string $type): self
    {
        return new self(\sprintf(
            'Translation root contract violated on %s::$%s: its type %s implements TranslationRootInterface AND TranslatableInterface, but a root is one row per object and has no locale -- the locale listeners would stamp and orphan-check it. '
            .'Solution: drop TranslatableInterface/TranslatableTrait from the root class and use TranslationRootTrait alone.',
            $class,
            $property,
            $type,
        ));
    }

    public static function forIdRootProperty(string $class, string $property): self
    {
        return new self(\sprintf(
            'Translation root contract violated on %s::$%s: the root reference carries #[ORM\Id], but PrimaryKeyHandler nulls every identifier on a clone before sharing is considered, so the reference would be lost on every translate(). '
            .'Solution: give the translation row its own generated identifier and keep the root reference a plain ManyToOne.',
            $class,
            $property,
        ));
    }

    public static function forEmptyOnTranslate(string $class, string $property): self
    {
        return new self(\sprintf(
            'Translation root contract violated on %s::$%s: the root reference carries #[EmptyOnTranslate], but every locale variant must point at the same root -- emptying it on translate() would orphan the new variant from its object. '
            .'Solution: remove #[EmptyOnTranslate] from the root reference.',
            $class,
            $property,
        ));
    }

    public static function forUniqueJoinColumn(string $class, string $property): self
    {
        return new self(\sprintf(
            'Translation root contract violated on %s::$%s: its #[ORM\JoinColumn] is unique, but one root has two or more translation rows by construction -- the second locale\'s INSERT would fail. '
            .'Solution: remove `unique: true` from the join column (and use ManyToOne, never OneToOne, for a root reference).',
            $class,
            $property,
        ));
    }

    public static function forMarkerWithoutRoot(string $class, string $property): self
    {
        return new self(\sprintf(
            'Translation root contract violated on %s::$%s: the property carries #[TranslationRoot] but is not a root reference -- a root reference is a #[ORM\ManyToOne] whose declared type implements TranslationRootInterface. '
            .'Solution: type the property to a TranslationRootInterface implementation and map it ManyToOne, or remove the marker.',
            $class,
            $property,
        ));
    }

    public static function forMissingRootConstructorParameter(string $class, string $property): self
    {
        return new self(\sprintf(
            'Translation root contract violated on %s: its root reference $%s is non-nullable, so the class is past the migration phase, but its constructor has no required, non-nullable parameter typed to TranslationRootInterface (or a subtype) -- a `new` without the root would let TranslatableTrait::getTuuid() lazily mint an identity the root never had. '
            .'Solution: add the root as a required constructor parameter and call $this->setTuuid($root->getTuuid()) there, or make the property nullable while the rows are still being adopted.',
            $class,
            $property,
        ));
    }

    public static function forMissingAdopter(string $class): self
    {
        return new self(\sprintf(
            'Translation root contract violated: %s declares a root reference but no service tagged `tmi_translation.root_adopter` names it (or an ancestor) in its `class` attribute, so tmi:translation:adopt-root cannot create roots for its existing rows. '
            .'Solution: register a RootAdopterInterface implementation tagged `tmi_translation.root_adopter` with `class: %s` (or the hierarchy root it belongs to).',
            $class,
            $class,
        ));
    }

    /**
     * @param list<string> $serviceIds
     */
    public static function forDuplicateAdopter(string $class, array $serviceIds): self
    {
        return new self(\sprintf(
            'Translation root contract violated: %s is served by %d root adopters (%s), but exactly one adopter decides how a group gets its root. '
            .'Solution: keep one `tmi_translation.root_adopter` service per translatable hierarchy.',
            $class,
            \count($serviceIds),
            implode(', ', $serviceIds),
        ));
    }

    public static function forAdopterWithoutRoot(string $serviceId, string $class): self
    {
        return new self(\sprintf(
            'Translation root contract violated: service "%s" is tagged `tmi_translation.root_adopter` for %s, but no concrete translatable class under it declares a root reference. '
            .'Solution: point the tag\'s `class` attribute at the translatable hierarchy whose rows reference a TranslationRootInterface, or remove the adopter.',
            $serviceId,
            $class,
        ));
    }

    public static function forAdopterTagWithoutClass(string $serviceId): self
    {
        return new self(\sprintf(
            'Translation root contract violated: service "%s" is tagged `tmi_translation.root_adopter` without the required `class` attribute, so the container cannot verify at compile time which translatable hierarchy it serves. '
            .'Solution: tag it as { name: tmi_translation.root_adopter, class: App\Entity\Property }.',
            $serviceId,
        ));
    }
}

<?php

namespace TMI\TranslationBundle\Utils;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Embedded;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;
use ReflectionAttribute;
use ReflectionProperty;
use TMI\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use TMI\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;

class AttributeHelper
{
    /**
     * Defines if the property is embedded
     */
    public function isEmbedded(ReflectionProperty $property): bool
    {
        return [] !== $property->getAttributes(Embedded::class, ReflectionAttribute::IS_INSTANCEOF);
    }

    /**
     * Defines if the property is to be shared amongst parents' translations
     */
    public function isSharedAmongstTranslations(ReflectionProperty $property): bool
    {
        return [] !== $property->getAttributes(SharedAmongstTranslations::class, ReflectionAttribute::IS_INSTANCEOF);
    }

    /**
     * Defines if the property should be emptied on translate
     */
    public function isEmptyOnTranslate(ReflectionProperty $property): bool
    {
        return [] !== $property->getAttributes(EmptyOnTranslate::class, ReflectionAttribute::IS_INSTANCEOF);
    }

    /**
     * Defines if the property is a OneToOne relation
     *
     *
     */
    public function isOneToOne(ReflectionProperty $property): bool
    {
        return [] !== $property->getAttributes(OneToOne::class, ReflectionAttribute::IS_INSTANCEOF);
    }

    /**
     * Defines if the property is an ID
     */
    public function isId(ReflectionProperty $property): bool
    {
        return [] !== $property->getAttributes(Id::class, ReflectionAttribute::IS_INSTANCEOF);
    }

    /**
     * Defines if the property is a ManyToOne relation
     */
    public function isManyToOne(ReflectionProperty $property): bool
    {
        return [] !== $property->getAttributes(ManyToOne::class, ReflectionAttribute::IS_INSTANCEOF);
    }

    /**
     * Defines if the property is a ManyToOne relation
     */
    public function isOneToMany(ReflectionProperty $property): bool
    {
        return [] !== $property->getAttributes(OneToMany::class, ReflectionAttribute::IS_INSTANCEOF);
    }

    /**
     * Defines if the property is a ManyToMany relation
     */
    public function isManyToMany(ReflectionProperty $property): bool
    {
        return [] !== $property->getAttributes(ManyToMany::class, ReflectionAttribute::IS_INSTANCEOF);
    }

    /**
     * Defines if the property can be null.
     */
    public function isNullable(ReflectionProperty $property): bool
    {
        $ra = $property->getAttributes(Column::class, ReflectionAttribute::IS_INSTANCEOF);

        if (0 < count($ra)) {
            $ra = reset($ra);
            $args = $ra->getArguments();

            return array_key_exists('nullable', $args) && true === $args['nullable'];
        }

        return true;
    }
}

<?php

namespace TMI\TranslationBundle\Doctrine\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;

use function in_array;

/**
 * Filters translatable contents by the current locale.
 */
final class LocaleFilter extends SQLFilter
{
    protected ?string $locale = null;

    /**
     * Dependency injection.
     */
    public function setLocale(?string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        if (null === $this->locale) {
            return '';
        }

        // If the entity is a TranslatableInterface
        if (in_array(TranslatableInterface::class, $targetEntity->getReflectionClass()?->getInterfaceNames(), true)) {
            return sprintf("%s.locale = '%s'", $targetTableAlias, $this->locale);
        }

        return '';
    }
}

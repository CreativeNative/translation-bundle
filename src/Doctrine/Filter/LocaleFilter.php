<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

/**
 * Filters translatable contents by the current locale.
 */
final class LocaleFilter extends SQLFilter
{
    /**
     * Dependency injection.
     */
    public function setLocale(string|null $locale): self
    {
        if (null !== $locale) {
            $this->setParameter('locale', $locale);
        }

        return $this;
    }

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$this->hasParameter('locale')) {
            return '';
        }

        $locale = $this->getParameter('locale');

        if (\in_array(TranslatableInterface::class, $targetEntity->getReflectionClass()?->getInterfaceNames(), true)) {
            return sprintf('%s.locale = %s', $targetTableAlias, $locale);
        }

        return '';
    }
}

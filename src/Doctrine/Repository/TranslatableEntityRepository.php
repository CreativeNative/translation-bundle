<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Repository;

use Doctrine\ORM\EntityRepository;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

/**
 * Ready-made repository base class for translatable entities.
 *
 * Extend this (or set it directly as `repositoryClass`) instead of hand-rolling
 * locale-variant lookups — those routinely get the `tmi_translation_locale_filter`
 * handling wrong and return only the current-locale row.
 *
 * @template T of TranslatableInterface
 *
 * @extends EntityRepository<T>
 */
class TranslatableEntityRepository extends EntityRepository
{
    use TranslatableRepositoryTrait;
}

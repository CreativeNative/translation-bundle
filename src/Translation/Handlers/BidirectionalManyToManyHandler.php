<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\ManyToMany;
use ErrorException;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Utils\AttributeHelper;

/**
 * Handles (and blocks) translation of ManyToMany relations.
 */
final readonly class BidirectionalManyToManyHandler implements TranslationHandlerInterface
{
    public function __construct(
        private AttributeHelper $attributeHelper
    ) {
    }

    public function supports(TranslationArgs $args): bool
    {
        $entity = $args->getDataToBeTranslated();

        if (!$entity instanceof TranslatableInterface) {
            return false;
        }

        $property = $args->getProperty();
        if (!$property || !$this->attributeHelper->isManyToMany($property)) {
            return false;
        }

        $attributes = $property->getAttributes(ManyToMany::class);
        if (empty($attributes)) {
            return false;
        }

        return true;
    }

    /**
     * @throws ErrorException
     */
    public function handleSharedAmongstTranslations(TranslationArgs $args): mixed
    {
        $data = $args->getDataToBeTranslated();

        $message =
            '%class%::%prop% is a ManyToMany relation. ManyToMany associations ' .
            'are not supported with translations, including the SharedAmongstTranslations attribute.';

        throw new ErrorException(
            strtr($message, [
                '%class%' => $data::class,
                '%prop%' => $args->getProperty()?->name ?? 'unknown',
            ])
        );
    }

    public function handleEmptyOnTranslate(TranslationArgs $args): Collection
    {
        return new ArrayCollection();
    }

    public function translate(TranslationArgs $args): Collection
    {
        $collection = $args->getDataToBeTranslated();

        return $collection instanceof Collection ? $collection : new ArrayCollection();
    }
}

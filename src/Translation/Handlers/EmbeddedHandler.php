<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Utils\AttributeHelper;

/**
 * Handler for Doctrine embeddable objects.
 */
final readonly class EmbeddedHandler implements TranslationHandlerInterface
{
    public function __construct(
        private AttributeHelper $attributeHelper,
        private DoctrineObjectHandler $objectHandler
    ) {
    }

    public function supports(TranslationArgs $args): bool
    {
        return null !== $args->getProperty() && $this->attributeHelper->isEmbedded($args->getProperty());
    }

    public function handleSharedAmongstTranslations(TranslationArgs $args): mixed
    {
        return $this->objectHandler->handleSharedAmongstTranslations($args);
    }

    public function handleEmptyOnTranslate(TranslationArgs $args): mixed
    {
        return $this->objectHandler->handleEmptyOnTranslate($args);
    }

    public function translate(TranslationArgs $args): mixed
    {
        // For embeddables we simply clone the object instance.
        return clone $args->getDataToBeTranslated();
    }
}

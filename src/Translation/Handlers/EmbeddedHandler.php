<?php

namespace TMI\TranslationBundle\Translation\Handlers;

use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Utils\AttributeHelper;

/**
 * Translation handler for @Doctrine\ORM\Mapping\Embeddable()
 */
class EmbeddedHandler implements TranslationHandlerInterface
{
    public function __construct(private readonly AttributeHelper $attributeHelper, private readonly DoctrineObjectHandler $objectHandler)
    {
    }

    public function supports(TranslationArgs $args): bool
    {
        return null !== $args->getProperty() && $this->attributeHelper->isEmbedded($args->getProperty());
    }

    public function handleSharedAmongstTranslations(TranslationArgs $args)
    {
        return $this->objectHandler->handleSharedAmongstTranslations($args);
    }

    public function handleEmptyOnTranslate(TranslationArgs $args)
    {
        return $this->objectHandler->handleEmptyOnTranslate($args);
    }

    public function translate(TranslationArgs $args)
    {
        return clone $args->getDataToBeTranslated();
    }
}

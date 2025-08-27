<?php

namespace TMI\TranslationBundle\Translation\Handlers;

use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Utils\AttributeHelper;

/**
 * Handles translation of primary keys.
 */
class PrimaryKeyHandler implements TranslationHandlerInterface
{
    public function __construct(private readonly AttributeHelper $attributeHelper)
    {
    }

    public function supports(TranslationArgs $args): bool
    {
        return null !== $args->getProperty() && $this->attributeHelper->isId($args->getProperty());
    }

    public function handleSharedAmongstTranslations(TranslationArgs $args)
    {
        return null;
    }

    public function handleEmptyOnTranslate(TranslationArgs $args)
    {
        return null;
    }

    public function translate(TranslationArgs $args)
    {
        return null;
    }
}

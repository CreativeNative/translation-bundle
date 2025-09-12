<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Utils\AttributeHelper;

/**
 * Handles translation of primary keys.
 */
final readonly class PrimaryKeyHandler implements TranslationHandlerInterface
{
    public function __construct(
        private AttributeHelper $attributeHelper
    ) {
    }

    public function supports(TranslationArgs $args): bool
    {
        return null !== $args->getProperty() && $this->attributeHelper->isId($args->getProperty());
    }

    public function handleSharedAmongstTranslations(TranslationArgs $args): null
    {
        return null;
    }

    public function handleEmptyOnTranslate(TranslationArgs $args): null
    {
        return null;
    }

    public function translate(TranslationArgs $args): null
    {
        return null;
    }
}

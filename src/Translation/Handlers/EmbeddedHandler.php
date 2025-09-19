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
        private AttributeHelper $attributeHelper
    ) {
    }

    public function supports(TranslationArgs $args): bool
    {
        return null !== $args->getProperty() && $this->attributeHelper->isEmbedded($args->getProperty());
    }

    public function handleSharedAmongstTranslations(TranslationArgs $args): mixed
    {
        return $args->getDataToBeTranslated();
    }

    public function handleEmptyOnTranslate(TranslationArgs $args): null
    {
        return null;
    }

    public function translate(TranslationArgs $args): mixed
    {
        return clone $args->getDataToBeTranslated();
    }
}

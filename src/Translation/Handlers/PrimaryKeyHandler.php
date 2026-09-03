<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Tmi\TranslationBundle\Translation\Context\TranslationContext;
use Tmi\TranslationBundle\Utils\AttributeHelper;

/**
 * Handles translation of primary keys.
 */
final readonly class PrimaryKeyHandler implements TranslationHandlerInterface
{
    public function __construct(
        private AttributeHelper $attributeHelper,
    ) {
    }

    public function supports(TranslationContext $context): bool
    {
        return null !== $context->getProperty() && $this->attributeHelper->isId($context->getProperty());
    }

    public function translate(TranslationContext $context): null
    {
        return null;
    }
}

<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Tmi\TranslationBundle\Translation\Context\TranslationContext;

interface TranslationHandlerInterface
{
    /**
     * Defines if the handler supports the data to be translated.
     */
    public function supports(TranslationContext $context): bool;

    /**
     * Handles translation.
     *
     * $context->isShared()/isEmpty() carry the #[SharedAmongstTranslations]/
     * #[EmptyOnTranslate] facts EntityTranslator already resolved from the property's
     * attributes before dispatching here -- a handler that cares branches on those
     * instead of the framework calling a second or third method.
     */
    public function translate(TranslationContext $context): mixed;
}

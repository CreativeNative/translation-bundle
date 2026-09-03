<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Tmi\TranslationBundle\Translation\Context\TranslationContext;

/**
 * Handles scalar type translation.
 */
final class ScalarHandler implements TranslationHandlerInterface
{
    public function supports(TranslationContext $context): bool
    {
        $data = $context->getSubject();

        return !\is_object($data) || $data instanceof \DateTime;
    }

    /**
     * Shared keeps the source value; empty clears it to null -- a scalar has no
     * meaningful non-null "empty" default of its own, and EntityTranslator already
     * falls back to TypeDefaultResolver for a non-nullable scalar property before
     * ever reaching here, so a scalar handler only sees isEmpty() on a nullable one.
     */
    public function translate(TranslationContext $context): mixed
    {
        if ($context->isEmpty()) {
            return null;
        }

        return $context->getSubject();
    }
}

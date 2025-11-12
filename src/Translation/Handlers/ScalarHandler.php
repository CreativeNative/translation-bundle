<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Tmi\TranslationBundle\Translation\Args\TranslationArgs;

/**
 * Handles scalar type translation.
 */
final class ScalarHandler implements TranslationHandlerInterface
{
    public function supports(TranslationArgs $args): bool
    {
        $data = $args->getDataToBeTranslated();

        return !\is_object($data) || $data instanceof \DateTime;
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
        return $args->getDataToBeTranslated();
    }
}

<?php

namespace TMI\TranslationBundle\Translation\Handlers;

use DateTime;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use function is_object;

/**
 * Handles scalar type translation.
 */
final class ScalarHandler implements TranslationHandlerInterface
{
    public function supports(TranslationArgs $args): bool
    {
        $data = $args->getDataToBeTranslated();
        return (!is_object($data) || $data instanceof DateTime);
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

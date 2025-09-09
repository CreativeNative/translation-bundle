<?php

namespace Tmi\TranslationBundle\Translation\Handlers;

use Tmi\TranslationBundle\Translation\Args\TranslationArgs;

interface TranslationHandlerInterface
{
    /**
     * Defines if the handler supports the data to be translated.
     *
     *
     */
    public function supports(TranslationArgs $args): bool;

    /**
     * Handles a SharedAmongstTranslations data translation.
     *
     *
     */
    public function handleSharedAmongstTranslations(TranslationArgs $args): mixed;

    /**
     * Handles an EmptyOnTranslate data translation.
     *
     *
     */
    public function handleEmptyOnTranslate(TranslationArgs $args): mixed;

    /**
     * Handles translation.
     *
     *
     */
    public function translate(TranslationArgs $args): mixed;
}

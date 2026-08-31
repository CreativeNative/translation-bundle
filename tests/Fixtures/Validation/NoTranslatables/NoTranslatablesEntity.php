<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\NoTranslatables;

/**
 * A concrete class that does not implement TranslatableInterface.
 *
 * Fixture for the "0 translatable entities discovered" compiler-pass log
 * branch: the attribute metadata driver directory exists and contains a PHP
 * class, but none of the classes in it are translatable.
 */
class NoTranslatablesEntity
{
}

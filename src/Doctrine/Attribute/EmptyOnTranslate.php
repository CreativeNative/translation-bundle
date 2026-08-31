<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Attribute;

/**
 * Intentionally inert on a class that does not implement TranslatableInterface,
 * for the same reason as #[SharedAmongstTranslations]: nothing reads this
 * attribute outside the translate() pipeline, so a shared trait can carry it
 * on a property without affecting the non-translatable classes that reuse it.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_CLASS)]
class EmptyOnTranslate
{
}

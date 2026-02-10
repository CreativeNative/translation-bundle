<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\ReadonlyEmpty;

use Tmi\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * Test fixture with readonly property having EmptyOnTranslate attribute.
 */
class ReadonlyEmptyEntity implements TranslatableInterface
{
    use TranslatableTrait;

    public function __construct(
        /** @phpstan-ignore property.onlyWritten */
        #[EmptyOnTranslate]
        private readonly string $readonlyProperty = '',
    ) {
    }
}

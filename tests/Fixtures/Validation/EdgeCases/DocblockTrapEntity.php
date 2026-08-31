<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\EdgeCases;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * Regression fixture for issue #2: a regex-based extractor matches the first
 * occurrence of "class Misleading" anywhere in the raw file text, including
 * inside a docblock like this one — even though no class named Misleading
 * exists anywhere in this file. The real declaration is below.
 *
 * describe() also exercises the two constructs a token walk must not mistake
 * for a declaration: `Foo::class` and `new class`.
 */
class DocblockTrapEntity implements TranslatableInterface
{
    use TranslatableTrait;

    public function describe(): string
    {
        $anonymous = new class {
        };

        return $anonymous::class.' '.TranslatableInterface::class;
    }
}

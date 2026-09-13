<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\Root\MissingConstructor;

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;
use Tmi\TranslationBundle\Fixtures\Validation\Root\Common\PlainRoot;

/**
 * A constructor that ACCEPTS the root but does not REQUIRE it (nullable, with a
 * default) does not satisfy the rule: `new OptionalConstructorEntity()` still lets
 * TranslatableTrait::getTuuid() mint an identity the root never had.
 */
class OptionalConstructorEntity implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\ManyToOne(targetEntity: PlainRoot::class)]
    /** @phpstan-ignore property.onlyWritten */
    private PlainRoot $root;

    public function __construct(PlainRoot|null $root = null, string $title = '')
    {
        $this->root = $root ?? new PlainRoot();
        $this->setLocale($title);
    }
}

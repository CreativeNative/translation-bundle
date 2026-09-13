<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\Root\UniqueJoinColumn;

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;
use Tmi\TranslationBundle\Fixtures\Validation\Root\Common\PlainRoot;

/**
 * A root reference with a unique join column -- the second locale's INSERT would
 * fail (TranslationRootContractException::forUniqueJoinColumn).
 */
class UniqueJoinColumnRootEntity implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\ManyToOne(targetEntity: PlainRoot::class)]
    #[ORM\JoinColumn(name: 'root_id', unique: true)]
    /** @phpstan-ignore property.onlyWritten, property.unusedType */
    private PlainRoot|null $root = null;
}

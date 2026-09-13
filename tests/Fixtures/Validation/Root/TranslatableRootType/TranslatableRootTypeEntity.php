<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\Root\TranslatableRootType;

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;
use Tmi\TranslationBundle\Fixtures\Validation\Root\Common\BothInterfacesRoot;

/**
 * A root reference whose type is ALSO translatable
 * (TranslationRootContractException::forTranslatableRootType).
 */
class TranslatableRootTypeEntity implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\ManyToOne(targetEntity: BothInterfacesRoot::class)]
    /** @phpstan-ignore property.onlyWritten, property.unusedType */
    private BothInterfacesRoot|null $root = null;
}

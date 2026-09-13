<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Validation\Root\Valid;

use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Attribute\TranslationRoot;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;
use Tmi\TranslationBundle\Fixtures\Validation\Root\Common\PlainRoot;

/**
 * Phase 1: nullable root reference, no constructor rule, the optional marker, and a
 * redundant #[SharedAmongstTranslations] that is tolerated.
 */
class Phase1Entity implements TranslatableInterface
{
    use TranslatableTrait;

    #[TranslationRoot]
    #[SharedAmongstTranslations]
    #[ORM\ManyToOne(targetEntity: PlainRoot::class)]
    #[ORM\JoinColumn(name: 'root_id', nullable: true)]
    /** @phpstan-ignore property.onlyWritten, property.unusedType */
    private PlainRoot|null $root = null;
}

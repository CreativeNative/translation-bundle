<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;

/**
 * Unidirectional ManyToOne associations (no inversedBy) -- the shape that falls
 * through every dedicated association handler's supports() (all five require
 * mappedBy/inversedBy or a ManyToMany-specific attribute) and reaches
 * TranslatableEntityHandler as the catch-all for any EntityTranslationContext.
 *
 * $sharedToTranslatable targets a TranslatableInterface entity (Scalar): sharing it
 * used to translate the target silently instead of sharing it, and is now rejected by
 * TranslatableEntityHandler::translate().
 *
 * $sharedToNonTranslatable targets a plain, non-translatable entity (reusing
 * NonTranslatableManyToOneBidirectionalChild, itself a bare Doctrine entity here --
 * its own back-reference to TranslatableOneToManyBidirectionalParent is left null):
 * sharing that is unaffected by this change and still returns the identical instance,
 * via DoctrineObjectHandler's own isShared() branch, since the property value never
 * becomes an EntityTranslationContext (it is not a TranslatableInterface).
 */
#[ORM\Entity]
class TranslatableManyToOneUnidirectional implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    #[SharedAmongstTranslations]
    #[ORM\ManyToOne(targetEntity: Scalar::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'shared_translatable_id', nullable: true)]
    private Scalar|null $sharedToTranslatable = null;

    #[SharedAmongstTranslations]
    #[ORM\ManyToOne(targetEntity: NonTranslatableManyToOneBidirectionalChild::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'shared_non_translatable_id', nullable: true)]
    private NonTranslatableManyToOneBidirectionalChild|null $sharedToNonTranslatable = null;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getSharedToTranslatable(): Scalar|null
    {
        return $this->sharedToTranslatable;
    }

    public function setSharedToTranslatable(Scalar|null $sharedToTranslatable = null): self
    {
        $this->sharedToTranslatable = $sharedToTranslatable;

        return $this;
    }

    public function getSharedToNonTranslatable(): NonTranslatableManyToOneBidirectionalChild|null
    {
        return $this->sharedToNonTranslatable;
    }

    public function setSharedToNonTranslatable(NonTranslatableManyToOneBidirectionalChild|null $sharedToNonTranslatable = null): self
    {
        $this->sharedToNonTranslatable = $sharedToNonTranslatable;

        return $this;
    }
}

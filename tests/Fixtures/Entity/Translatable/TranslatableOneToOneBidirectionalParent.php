<?php

namespace TMI\TranslationBundle\Fixtures\Entity\Translatable;

use Doctrine\ORM\Mapping as ORM;
use TMI\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use TMI\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
#[ORM\Table]
class TranslatableOneToOneBidirectionalParent implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    protected ?int $id = null;

    /**
     * @var mixed
     */
    #[ORM\OneToOne(targetEntity: \TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneBidirectionalChild::class, mappedBy: 'simpleParent', cascade: ['all'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?\TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneBidirectionalChild $simpleChild = null;

    /**
     * @var mixed
     *
     * @SharedAmongstTranslations()
     */
    #[ORM\OneToOne(targetEntity: \TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneBidirectionalChild::class, mappedBy: 'sharedParent')]
    #[ORM\JoinColumn(nullable: true)]
    private ?\TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneBidirectionalChild $sharedChild = null;

    /**
     * @var mixed
     *
     * @EmptyOnTranslate()
     */
    #[ORM\OneToOne(targetEntity: \TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneBidirectionalChild::class, mappedBy: 'emptyParent')]
    #[ORM\JoinColumn(nullable: true)]
    private ?\TMI\TranslationBundle\Fixtures\Entity\Translatable\TranslatableOneToOneBidirectionalChild $emptyChild = null;

    /**
     * @return mixed
     */
    public function getSimpleChild()
    {
        return $this->simpleChild;
    }

    /**
     * @param mixed $simpleChild
     *
     * @return $this
     */
    public function setSimpleChild($simpleChild)
    {
        $this->simpleChild = $simpleChild;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getSharedChild()
    {
        return $this->sharedChild;
    }

    /**
     * @param mixed $sharedChild
     *
     * @return $this
     */
    public function setSharedChild($sharedChild)
    {
        $this->sharedChild = $sharedChild;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getEmptyChild()
    {
        return $this->emptyChild;
    }

    /**
     * @param mixed $emptyChild
     *
     * @return $this
     */
    public function setEmptyChild($emptyChild)
    {
        $this->emptyChild = $emptyChild;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }
}

<?php

namespace  TMI\TranslationBundle\Fixtures\Entity\Scalar;

use Doctrine\ORM\Mapping as ORM;
use TMI\TranslationBundle\Doctrine\Attribute\EmptyOnTranslate;
use TMI\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
#[ORM\Table]
class Scalar implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;

    #[ORM\Column(type: 'string', nullable: true)]
    protected ?string $title = null;

    /**
     * @SharedAmongstTranslations()
     */
    #[ORM\Column(type: 'string', nullable: true)]
    protected ?string $shared = null;

    /**
     * Scalar value.
     *
     * @var string
     * @EmptyOnTranslate()
     */
    #[ORM\Column(type: 'string', nullable: true)]
    protected ?string $empty = null;

    /**
     * @param string $title
     *
     * @return Scalar
     */
    public function setTitle(?string $title = null)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $shared
     *
     * @return Scalar
     */
    public function setShared(?string $shared = null)
    {
        $this->shared = $shared;

        return $this;
    }

    /**
     * @return string
     */
    public function getShared()
    {
        return $this->shared;
    }

    /**
     * @param string $empty
     *
     * @return Scalar
     */
    public function setEmpty(?string $empty = null)
    {
        $this->empty = $empty;

        return $this;
    }

    /**
     * @return string
     */
    public function getEmpty()
    {
        return $this->empty;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }
}

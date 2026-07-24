<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\ReadonlyShared;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

#[ORM\Entity]
class ReadonlyShared implements TranslatableInterface
{
    use TranslatableTrait;

    #[SharedAmongstTranslations]
    #[ORM\Column(type: Types::STRING)]
    public readonly string $sku;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int|null $id = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $title = null;

    /** Writable shared sibling of $sku: readonly drift must not stop this one from syncing. */
    #[SharedAmongstTranslations]
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $note = null;

    public function __construct(string $sku = '')
    {
        $this->sku = $sku;
    }

    public function getId(): int|null
    {
        return $this->id;
    }

    public function setTitle(string|null $title = null): self
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): string|null
    {
        return $this->title;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function setNote(string|null $note = null): self
    {
        $this->note = $note;

        return $this;
    }

    public function getNote(): string|null
    {
        return $this->note;
    }
}

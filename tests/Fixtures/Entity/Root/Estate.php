<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Root;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Attribute\SharedAmongstTranslations;
use Tmi\TranslationBundle\Doctrine\Attribute\TranslationRoot;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * The translation rows of a {@see Listing} root, PHASE 1 of the rollout (5.1): the
 * root reference is nullable, no constructor rule applies, and
 * `tmi:translation:adopt-root` fills the FK for rows that predate their root. A
 * SINGLE_TABLE hierarchy whose abstract root declares the reference once for both
 * leaves ({@see EstateA}, {@see EstateB}) -- the application's `Property` shape.
 *
 * The optional #[TranslationRoot] marker is present here and deliberately absent on
 * {@see Article}: the reference is a root reference by its TYPE either way.
 */
#[ORM\Entity]
#[ORM\Table(name: 'root_estate')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'kind', type: 'string')]
#[ORM\DiscriminatorMap(['a' => EstateA::class, 'b' => EstateB::class])]
abstract class Estate implements TranslatableInterface
{
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $title = null;

    #[SharedAmongstTranslations]
    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $family = 'default';

    #[ORM\ManyToOne(targetEntity: Listing::class)]
    #[ORM\JoinColumn(name: 'listing_id', nullable: true)]
    #[TranslationRoot]
    private Listing|null $listing = null;

    public function getId(): int|null
    {
        return $this->id;
    }

    public function getTitle(): string|null
    {
        return $this->title;
    }

    public function setTitle(string|null $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getFamily(): string
    {
        return $this->family;
    }

    public function setFamily(string $family): static
    {
        $this->family = $family;

        return $this;
    }

    public function getListing(): Listing|null
    {
        return $this->listing;
    }

    public function setListing(Listing|null $listing): static
    {
        $this->listing = $listing;

        return $this;
    }
}

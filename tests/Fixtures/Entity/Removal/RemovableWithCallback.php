<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Removal;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

/**
 * Translatable entity with a consumer-style `#[ORM\PreRemove]` lifecycle
 * callback and a static call counter.
 *
 * Exists to prove that {@see \Tmi\TranslationBundle\Doctrine\EventListener\LocaleVariantRemovalListener}
 * removes every sibling locale variant through `EntityManager::remove()` —
 * so Doctrine's own `preRemove` lifecycle fires exactly once per variant —
 * rather than through some shortcut that would bypass it.
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class RemovableWithCallback implements TranslatableInterface
{
    use TranslatableTrait;

    public static int $preRemoveCallCount = 0;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int|null $id = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private string|null $title = null;

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

    #[ORM\PreRemove]
    public function onPreRemove(): void
    {
        ++self::$preRemoveCallCount;
    }
}

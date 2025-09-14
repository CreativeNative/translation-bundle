<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Twig;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigTest;

final class TmiTranslationExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @param array<string> $locales
     */
    public function __construct(private readonly array $locales)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getTests(): array
    {
        return [
            new TwigTest('translatable', fn($object) => $object instanceof TranslatableInterface)
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getGlobals(): array
    {
        return [
            'locales' => $this->locales,
        ];
    }
}

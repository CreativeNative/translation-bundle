<?php

namespace Tmi\TranslationBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigTest;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;

final class TmiTranslationExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private readonly array $locales)
    {
    }

    public function getTests(): array
    {
        return [
            new TwigTest('translatable', fn($object) => $object instanceof TranslatableInterface)
        ];
    }

    public function getGlobals(): array
    {
        return [
            'locales' => $this->locales,
        ];
    }
}

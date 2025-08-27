<?php

namespace TMI\TranslationBundle\Twig;

use JetBrains\PhpStorm\ArrayShape;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigTest;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;

class UmanitTranslationExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private readonly array $locales)
    {
    }

    #[\Override]
    public function getTests(): array
    {
        return [
            new TwigTest('translatable', fn($object) => $object instanceof TranslatableInterface),
        ];
    }

    #[ArrayShape(['locales' => "array"])]
    public function getGlobals(): array
    {
        return [
            'locales' => $this->locales,
        ];
    }
}

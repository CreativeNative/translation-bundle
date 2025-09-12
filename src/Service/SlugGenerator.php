<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Service;

use Symfony\Component\String\Slugger\AsciiSlugger;

final readonly class SlugGenerator
{
    private AsciiSlugger $slugger;

    public function __construct()
    {
        $this->slugger = new AsciiSlugger();
    }

    public function generate(string $text, string $locale): string
    {
        return $this->slugger->slug($text, '-', $locale)
            ->lower()
            ->toString();
    }
}

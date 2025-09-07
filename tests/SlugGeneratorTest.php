<?php

namespace TMI\TranslationBundle\Test;

use PHPUnit\Framework\TestCase;
use TMI\TranslationBundle\Service\SlugGenerator;

final class SlugGeneratorTest extends TestCase
{
    public function testSlugGeneration(): void
    {
        $slugger = new SlugGenerator();
        $slug = $slugger->generate("Ciao Sicilia", "it");

        self::assertSame("ciao-sicilia", $slug);
    }
}

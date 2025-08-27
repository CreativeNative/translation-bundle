<?php

namespace TMI\TranslationBundle\Test;

use PHPUnit\Framework\TestCase;
use TMI\TranslationBundle\Service\SlugGenerator;

class SlugGeneratorTest extends TestCase
{
    public function testSlugGeneration(): void
    {
        $slugger = new SlugGenerator();
        $slug = $slugger->generate("Ciao Sicilia", "it");

        $this->assertSame("ciao-sicilia", $slug);
    }
}

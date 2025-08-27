<?php

namespace TMI\TranslationBundle\Test;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use TMI\TranslationBundle\Service\SlugGenerator;

class SlugGeneratorTest extends TestCase
{
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new TestKernel('test', true);
    }

    public function testSlugGeneration(): void
    {
        $slugger = new SlugGenerator();
        $slug = $slugger->generate("Ciao Sicilia", "it");

        $this->assertSame("ciao-sicilia", $slug);
    }
}

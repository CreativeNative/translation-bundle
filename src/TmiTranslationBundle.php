<?php

namespace TMI\TranslationBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use TMI\TranslationBundle\DependencyInjection\Compiler\TranslationHandlerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class TmiTranslationBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new TranslationHandlerPass());
    }
}

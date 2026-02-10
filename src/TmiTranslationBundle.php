<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tmi\TranslationBundle\DependencyInjection\Compiler\AttributeValidationPass;
use Tmi\TranslationBundle\DependencyInjection\Compiler\TranslationHandlerPass;

final class TmiTranslationBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new TranslationHandlerPass());
        $container->addCompilerPass(new AttributeValidationPass());
    }
}

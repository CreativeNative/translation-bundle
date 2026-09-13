<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tmi\TranslationBundle\DependencyInjection\Compiler\AttributeValidationPass;
use Tmi\TranslationBundle\DependencyInjection\Compiler\RootAdopterPass;
use Tmi\TranslationBundle\DependencyInjection\Compiler\TranslationHandlerPass;
use Tmi\TranslationBundle\DependencyInjection\Compiler\TuuidOrphanCounterPass;

final class TmiTranslationBundle extends Bundle
{
    #[\Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new TranslationHandlerPass());
        $container->addCompilerPass(new AttributeValidationPass());
        // After AttributeValidationPass: the adopter cross-check reads the
        // `tmi_translation.translation_root_classes` parameter that pass computes.
        $container->addCompilerPass(new RootAdopterPass());
        $container->addCompilerPass(new TuuidOrphanCounterPass());
    }
}

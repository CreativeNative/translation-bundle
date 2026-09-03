<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Reflection\OneToMany;

use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableTrait;

final class InheritedBackReferenceChild extends InheritedBackReferenceSuperclass implements TranslatableInterface
{
    use TranslatableTrait;
}

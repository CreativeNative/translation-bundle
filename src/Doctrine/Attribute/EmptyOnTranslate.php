<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Doctrine\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class EmptyOnTranslate
{
}

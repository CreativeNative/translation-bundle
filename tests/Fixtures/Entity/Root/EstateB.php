<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Root;

use Doctrine\ORM\Mapping as ORM;

/**
 * Rows whose root is a {@see ListingB}.
 */
#[ORM\Entity]
class EstateB extends Estate
{
}

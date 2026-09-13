<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Entity\Root;

use Doctrine\ORM\Mapping as ORM;

/**
 * Rows whose root is a {@see ListingA}.
 */
#[ORM\Entity]
class EstateA extends Estate
{
}

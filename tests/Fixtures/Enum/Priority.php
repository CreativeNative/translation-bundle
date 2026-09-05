<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Enum;

/**
 * Backing enum for {@see \Tmi\TranslationBundle\Fixtures\Entity\SharedEnum\SharedEnum}.
 * Lives outside the mapped entity directory so neither Doctrine's attribute
 * driver nor AttributeValidationPass ever tries to treat it as an entity.
 */
enum Priority: string
{
    case Low  = 'low';
    case High = 'high';
}

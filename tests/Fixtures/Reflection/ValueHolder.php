<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Fixtures\Reflection;

use Tmi\TranslationBundle\Fixtures\Entity\Embedded\Address;
use Tmi\TranslationBundle\Fixtures\Enum\Priority;

/**
 * A plain (unmapped) value object holding one property of every kind
 * SharedValueRenderer renders one level deep -- and one it leaves uninitialized.
 */
final class ValueHolder
{
    public Address $address;

    public \DateTimeImmutable $at;

    public Priority $priority;

    public string $later;

    public function __construct()
    {
        $this->address  = new Address();
        $this->at       = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $this->priority = Priority::Low;
    }
}

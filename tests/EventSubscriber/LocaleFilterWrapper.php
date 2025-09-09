<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\EventSubscriber;

use Doctrine\ORM\Query\Filter\SQLFilter;

class LocaleFilterWrapper
{
    public function __construct(private SQLFilter $filter)
    {
    }

    public function setLocale(string $locale): void
    {
        $this->filter->setParameter('locale', $locale);
    }
}

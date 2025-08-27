<?php

namespace TMI\TranslationBundle\Test;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use TMI\TranslationBundle\Translation\EntityTranslator;

abstract class AbstractBaseTest extends KernelTestCase
{
    protected EntityTranslator $translator;

    protected EntityManagerInterface $em;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        self::bootKernel();

        $this->translator = static::getContainer()
            ->get(EntityTranslator::class);

        $this->em = static::getContainer()
            ->get(EntityManagerInterface::class);
    }
}

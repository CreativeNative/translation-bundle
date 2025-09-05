<?php

namespace TMI\TranslationBundle\Translation\Handlers;

use Doctrine\ORM\EntityManagerInterface;
use ReflectionException;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;

final readonly class TranslatableEntityHandler implements TranslationHandlerInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private DoctrineObjectHandler $doctrineObjectHandler
    ) {
    }

    public function supports(TranslationArgs $args): bool
    {
        return $args->getDataToBeTranslated() instanceof TranslatableInterface;
    }

    /**
     * @throws ReflectionException
     */
    public function handleSharedAmongstTranslations(TranslationArgs $args): TranslatableInterface
    {
        return $this->translate($args);
    }

    public function handleEmptyOnTranslate(TranslationArgs $args): null
    {
        return null;
    }

    /**
     * @throws ReflectionException
     */
    public function translate(TranslationArgs $args): TranslatableInterface
    {
        $data = $args->getDataToBeTranslated();

        // Search in database if the content exists, otherwise translate it.
        $existingTranslation = $this->em->getRepository($data::class)->findOneBy([
            'locale' => $args->getTargetLocale(),
            'tuuid'  => $data->getTuuid(),
        ]);

        if (null !== $existingTranslation) {
            assert($existingTranslation instanceof TranslatableInterface);
            return $existingTranslation;
        }

        $clone = clone $data;
        assert($clone instanceof TranslatableInterface);

        $this->doctrineObjectHandler->translateProperties(
            new TranslationArgs($clone, $clone->getLocale(), $args->getTargetLocale())
        );

        $clone->setLocale($args->getTargetLocale());

        return $clone;
    }
}

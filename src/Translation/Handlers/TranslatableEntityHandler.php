<?php

namespace TMI\TranslationBundle\Translation\Handlers;

use Doctrine\ORM\EntityManagerInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;

class TranslatableEntityHandler implements TranslationHandlerInterface
{
    public function __construct(protected EntityManagerInterface $em, protected DoctrineObjectHandler $doctrineObjectHandler)
    {
    }

    public function supports(TranslationArgs $args): bool
    {
        return $args->getDataToBeTranslated() instanceof TranslatableInterface;
    }

    public function handleSharedAmongstTranslations(TranslationArgs $args)
    {
        return $this->translate($args);
    }

    public function handleEmptyOnTranslate(TranslationArgs $args)
    {
        return null;
    }

    public function translate(TranslationArgs $args)
    {
        $data = $args->getDataToBeTranslated();

        // Search in database if the content
        // exists, otherwise translate it.
        $existingTranslation = $this->em->getRepository($data::class)->findOneBy([
            'locale' => $args->getTargetLocale(),
            'tuuid'  => $data->getTuuid(),
        ]);

        if (null !== $existingTranslation) {
            return $existingTranslation;
        }

        /** @var TranslatableInterface $clone */
        $clone = clone $args->getDataToBeTranslated();

        $this->doctrineObjectHandler->translateProperties(
            new TranslationArgs($clone, $clone->getLocale(), $args->getTargetLocale())
        );

        $clone->setLocale($args->getTargetLocale());

        $this->em->persist($clone);

        return $clone;
    }
}

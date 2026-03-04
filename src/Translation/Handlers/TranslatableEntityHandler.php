<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation\Handlers;

use Doctrine\ORM\EntityManagerInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Utils\AttributeHelper;

final readonly class TranslatableEntityHandler implements TranslationHandlerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DoctrineObjectHandler $doctrineObjectHandler,
        private AttributeHelper $attributeHelper,
    ) {
    }

    public function supports(TranslationArgs $args): bool
    {
        return $args->getDataToBeTranslated() instanceof TranslatableInterface;
    }

    /**
     * @throws \ReflectionException
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
     * @throws \ReflectionException
     */
    public function translate(TranslationArgs $args): TranslatableInterface
    {
        $data = $args->getDataToBeTranslated();
        \assert($data instanceof TranslatableInterface);

        // Search in database if the content exists, otherwise translate it.
        $existingTranslation = $this->entityManager->getRepository($data::class)->findOneBy([
            'locale' => $args->getTargetLocale(),
            'tuuid'  => (string) $data->getTuuid(),
        ]);

        if ($existingTranslation instanceof TranslatableInterface) {
            return $existingTranslation;
        }

        $clone = clone $data;

        $subArgs = new TranslationArgs($clone, $clone->getLocale(), $args->getTargetLocale());
        $subArgs->setCopySource($args->getCopySource());
        $this->doctrineObjectHandler->translateProperties($subArgs);

        $this->resetGeneratedIds($clone);
        $clone->setLocale($args->getTargetLocale());

        return $clone;
    }

    private function resetGeneratedIds(TranslatableInterface $clone): void
    {
        $reflection = new \ReflectionClass($clone);

        foreach ($reflection->getProperties() as $property) {
            if ($this->attributeHelper->isId($property) && $this->attributeHelper->isGeneratedValue($property)) {
                $property->setValue($clone, null);
            }
        }
    }
}

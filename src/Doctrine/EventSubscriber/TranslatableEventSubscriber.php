<?php

namespace TMI\TranslationBundle\Doctrine\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
class TranslatableEventSubscriber
{
    private array $entitiesToUpdate = [];
    private array $entitiesToDelete = [];

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->setDefaultValues($args);
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->updateTranslations($args);
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->updateTranslations($args);
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->removeAllTranslations($args);
    }

    private function setDefaultValues(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof TranslatableInterface) {
            return;
        }

        $objectManager = $args->getObjectManager();

        if (null === $entity->getTuuid()) {
            $entity->setTuuid($this->generateUuid());
        }

        if (null === $entity->getLocale()) {
            $entity->setLocale($this->getDefaultLocale());
        }

        $this->entitiesToUpdate[] = $entity;
    }

    private function removeAllTranslations(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof TranslatableInterface) {
            return;
        }

        $this->entitiesToDelete[] = $entity;
    }

    /**
     * @throws MappingException
     */
    private function updateTranslations(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof TranslatableInterface) {
            return;
        }

        if ($this->entitiesToUpdate === []) {
            return;
        }

        foreach ($this->entitiesToUpdate as $key => $entityToUpdate) {
            if ($entityToUpdate === $entity) {
                unset($this->entitiesToUpdate[$key]);
                $this->synchronizeTranslatableSharedField($args);
                break;
            }
        }
    }

    /**
     * @throws MappingException
     */
    private function synchronizeTranslatableSharedField(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof TranslatableInterface) {
            return;
        }

        $objectManager = $args->getObjectManager();
        $classMetadata = $objectManager->getClassMetadata(get_class($entity));
        $fieldNames = $classMetadata->getFieldNames();

        foreach ($fieldNames as $fieldName) {
            $fieldMapping = $classMetadata->getFieldMapping($fieldName);
            if (isset($fieldMapping['options']['translatable']) && $fieldMapping['options']['translatable'] === true) {
                $getter = 'get' . ucfirst($fieldName);
                $setter = 'set' . ucfirst($fieldName);
                if (method_exists($entity, $getter) && method_exists($entity, $setter)) {
                    // @phpstan-ignore-next-line method.dynamicName
                    $value = $entity->{$getter}();
                    // @phpstan-ignore-next-line method.dynamicName
                    $entity->{$setter}($value);
                }
            }
        }
    }

    private function generateUuid(): string
    {
        return uniqid('', true);
    }

    private function getDefaultLocale(): string
    {
        return 'en';
    }
}
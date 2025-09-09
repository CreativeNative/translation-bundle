<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Translation;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tmi\TranslationBundle\Doctrine\Model\TranslatableInterface;
use Tmi\TranslationBundle\Translation\Args\TranslationArgs;
use Tmi\TranslationBundle\Translation\Handlers\TranslationHandlerInterface;
use Tmi\TranslationBundle\Utils\AttributeHelper;

final class EntityTranslator implements EntityTranslatorInterface
{
    /** @var TranslationHandlerInterface[] */
    private array $handlers = [];

    public function __construct(
        #[Autowire(param: 'tmi_translation.default_locale')]
        private readonly string $defaultLocale,
        private readonly array $locales,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AttributeHelper $attributeHelper
    ) {
    }

    /**
     * Translate an entity to a target locale.
     */
    public function translate(TranslatableInterface $entity, string $locale): TranslatableInterface
    {
        return $this->processTranslation(new TranslationArgs($entity, $entity->getLocale(), $locale));
    }

    /**
     * Process the translation via handlers.
     */
    public function processTranslation(TranslationArgs $args): mixed
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($args)) {
                if (null !== $args->getProperty()) {
                    if ($this->attributeHelper->isSharedAmongstTranslations($args->getProperty())) {
                        return $handler->handleSharedAmongstTranslations($args);
                    }

                    if ($this->attributeHelper->isEmptyOnTranslate($args->getProperty())) {
                        if (!$this->attributeHelper->isNullable($args->getProperty())) {
                            throw new LogicException(sprintf(
                                'The property %s::%s cannot use EmptyOnTranslate because it is not nullable.',
                                $args->getProperty()->class,
                                $args->getProperty()->name
                            ));
                        }
                        return $handler->handleEmptyOnTranslate($args);
                    }
                }
                return $handler->translate($args);
            }
        }
        return $args->getDataToBeTranslated();
    }

    /**
     * Register a handler.
     */
    public function addTranslationHandler(TranslationHandlerInterface $handler, ?int $priority = null): void
    {
        if (null === $priority) {
            $this->handlers[] = $handler;
        } else {
            $this->handlers[$priority] = $handler;
        }
    }

    // --- EntityTranslatorInterface Hooks ---

    public function afterLoad(TranslatableInterface $entity): void
    {
        $this->translate($entity, $entity->getLocale() ?? $this->defaultLocale);
    }

    public function beforePersist(TranslatableInterface $entity, EntityManagerInterface $em): void
    {
        $this->translate($entity, $entity->getLocale() ?? $this->defaultLocale);
    }

    public function beforeUpdate(TranslatableInterface $entity, EntityManagerInterface $em): void
    {
        $this->translate($entity, $entity->getLocale() ?? $this->defaultLocale);
    }

    public function beforeRemove(TranslatableInterface $entity, EntityManagerInterface $em): void
    {
        // ToDo: Optional cleanup or handler logic
        $this->translate($entity, $entity->getLocale() ?? $this->defaultLocale);
    }
}

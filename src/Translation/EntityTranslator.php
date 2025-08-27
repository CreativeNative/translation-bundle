<?php

namespace TMI\TranslationBundle\Translation;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use TMI\TranslationBundle\Doctrine\Model\TranslatableInterface;
use TMI\TranslationBundle\Translation\Args\TranslationArgs;
use TMI\TranslationBundle\Translation\Handlers\TranslationHandlerInterface;
use TMI\TranslationBundle\Utils\AttributeHelper;

class EntityTranslator
{
    protected array $handlers;

    public function __construct(protected array $locales, protected EventDispatcherInterface $eventDispatcher, private readonly AttributeHelper $attributeHelper)
    {
    }

    /**
     * Translates a given entity
     */
    public function translate(TranslatableInterface $data, string $locale)
    {
        return $this->processTranslation(new TranslationArgs($data, $data->getLocale(), $locale));
    }

    /**
     * Processes the translation
     */
    public function processTranslation(TranslationArgs $args)
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($args)) {
                if (null !== $args->getProperty()) {
                    if ($this->attributeHelper->isSharedAmongstTranslations($args->getProperty())) {
                        return $handler->handleSharedAmongstTranslations($args);
                    }

                    if ($this->attributeHelper->isEmptyOnTranslate($args->getProperty())) {
                        if (!$this->attributeHelper->isNullable($args->getProperty())) {
                            throw new \LogicException(
                                sprintf(
                                    'The property %s::%s can not use the EmptyOnTranslate attribute because it is not nullable.',
                                    $args->getProperty()->class,
                                    $args->getProperty()->name
                                )
                            );
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
     * Service call
     */
    public function addTranslationHandler(TranslationHandlerInterface $handler, $priority = null): void
    {
        if (null === $priority) {
            $this->handlers[] = $handler;
        } else {
            $this->handlers[$priority] = $handler;
        }
    }
}

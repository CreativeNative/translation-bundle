<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Tmi\TranslationBundle\Doctrine\Filter\LocaleFilter;

#[AsEventListener(event: KernelEvents::REQUEST, method: 'onKernelRequest', priority: 2)]
final readonly class LocaleFilterConfigurator implements EventSubscriberInterface
{
    /**
     * @param array<string> $disabledFirewalls
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private array $disabledFirewalls,
        private FirewallMap|null $firewallMap = null,
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => [['onKernelRequest', 2]]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $filters = $this->entityManager->getFilters();

        if (!$filters->has('tmi_translation_locale_filter')) {
            return;
        }

        $request = $event->getRequest();

        if ($this->isDisabledFirewall($request)) {
            if ($filters->isEnabled('tmi_translation_locale_filter')) {
                $filters->disable('tmi_translation_locale_filter');
            }

            return;
        }

        $filter = $filters->enable('tmi_translation_locale_filter');
        assert($filter instanceof LocaleFilter);
        $filter->setLocale($request->getLocale());
    }

    private function isDisabledFirewall(Request $request): bool
    {
        if (null === $this->firewallMap) {
            return false;
        }

        $config = $this->firewallMap->getFirewallConfig($request);
        if (null === $config) {
            return false;
        }

        return \in_array($config->getName(), $this->disabledFirewalls, true);
    }
}

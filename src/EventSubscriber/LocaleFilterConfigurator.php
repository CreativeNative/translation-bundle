<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Tmi\TranslationBundle\Doctrine\Filter\LocaleFilter;

#[AsEventListener(event: KernelEvents::REQUEST, method: 'onKernelRequest', priority: 2)]
#[AsEventListener(event: KernelEvents::FINISH_REQUEST, method: 'onKernelFinishRequest', priority: 2)]
final readonly class LocaleFilterConfigurator implements EventSubscriberInterface
{
    /**
     * @param array<string> $disabledFirewalls
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private array $disabledFirewalls,
        private FirewallMap|null $firewallMap = null,
        private RequestStack|null $requestStack = null,
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST        => [['onKernelRequest', 2]],
            KernelEvents::FINISH_REQUEST => [['onKernelFinishRequest', 2]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $this->applyForRequest($event->getRequest());
    }

    /**
     * Restores the parent request's locale once a sub-request finishes.
     *
     * The filter lives on the shared EntityManager, so a fragment, ESI or forward would
     * otherwise leave every later query in the parent request filtered by the sub-request's
     * locale. Mirrors what Symfony's own LocaleAwareListener does for locale-aware services.
     */
    public function onKernelFinishRequest(FinishRequestEvent $event): void
    {
        $parentRequest = $this->requestStack?->getParentRequest();

        if (null === $parentRequest) {
            return;
        }

        $this->applyForRequest($parentRequest);
    }

    private function applyForRequest(Request $request): void
    {
        $filters = $this->entityManager->getFilters();

        if (!$filters->has('tmi_translation_locale_filter')) {
            return;
        }

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

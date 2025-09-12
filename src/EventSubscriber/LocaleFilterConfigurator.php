<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Tmi\TranslationBundle\Doctrine\Filter\LocaleFilter;

use function in_array;

final readonly class LocaleFilterConfigurator implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private array $disabledFirewalls,
        private FirewallMap|null $firewallMap = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => [['onKernelRequest', 2]]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $filters = $this->em->getFilters();

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
        if ($this->firewallMap === null) {
            return false;
        }

        $config = $this->firewallMap->getFirewallConfig($request);
        if ($config === null) {
            return false;
        }

        return in_array($config->getName(), $this->disabledFirewalls, true);
    }
}

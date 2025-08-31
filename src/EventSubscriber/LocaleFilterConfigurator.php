<?php

namespace TMI\TranslationBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use TMI\TranslationBundle\Doctrine\Filter\LocaleFilter;

/**
 * Configure the LocaleFilter as it's not a service but has dependencies.
 */
class LocaleFilterConfigurator implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly array $disabledFirewalls,
        private readonly ?FirewallMap $firewallMap = null
    )
    {
    }

    public static function getSubscribedEvents()
    {
        return [KernelEvents::REQUEST => [['onKernelRequest', 2]]];
    }

    /**
     * Called on each request.
     *
     * @param RequestEvent $event
     */
    public function onKernelRequest(RequestEvent $event)
    {
        if ($this->em->getFilters()->has('tmi_translation_locale_filter')) {
            if ($this->isDisabledFirewall($event->getRequest())) {
                if ($this->em->getFilters()->isEnabled('tmi_translation_locale_filter')) {
                    $this->em->getFilters()->disable('tmi_translation_locale_filter');
                }

                return;
            }

            /** @var LocaleFilter $filter */
            $filter = $this->em->getFilters()->enable('tmi_translation_locale_filter');

            $filter->setLocale($event->getRequest()->getLocale());
        }
    }

    /**
     * Indicates if the current firewall should disable the filter.
     */
    protected function isDisabledFirewall(Request $request): bool
    {
        if (null === $this->firewallMap || null === $this->firewallMap->getFirewallConfig($request)) {
            return false;
        }

        return \in_array($this->firewallMap->getFirewallConfig($request)->getName(), $this->disabledFirewalls, true);
    }
}

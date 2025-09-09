<?php

namespace Tmi\TranslationBundle\Test\EventSubscriber;

use Doctrine\ORM\Query\FilterCollection;
use ReflectionClass;
use ReflectionException;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Tmi\TranslationBundle\EventSubscriber\LocaleFilterConfigurator;
use Tmi\TranslationBundle\Doctrine\Filter\LocaleFilter;
use Tmi\TranslationBundle\Test\TestCase;

final class LocaleFilterConfiguratorTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = LocaleFilterConfigurator::getSubscribedEvents();
        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
        $this->assertSame([['onKernelRequest', 2]], $events[KernelEvents::REQUEST]);
    }

    public function testFilterIsEnabledAndLocaleSet(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $request->setLocale('en');

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Subscriber without disabled firewalls
        $subscriber = new LocaleFilterConfigurator($this->entityManager, []);
        $subscriber->onKernelRequest($event);

        $filter = $this->entityManager->getFilters()->getFilter('tmi_translation_locale_filter');

        $this->assertInstanceOf(LocaleFilter::class, $filter);
        $this->assertSame("'en'", $filter->getParameter('locale')); // Doctrine stores parameter in SQL form
    }

    public function testFilterCanChangeLocale(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $request->setLocale('fr');

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber = new LocaleFilterConfigurator($this->entityManager, []);
        $subscriber->onKernelRequest($event);

        $filter = $this->entityManager->getFilters()->getFilter('tmi_translation_locale_filter');
        $this->assertInstanceOf(LocaleFilter::class, $filter);
        $this->assertSame("'fr'", $filter->getParameter('locale'));
    }

    public function testFilterDisabledForDisabledFirewall(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $request->setLocale('en');

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $firewallMap = $this->createMock(FirewallMap::class);
        $firewallMap->method('getFirewallConfig')
            ->willReturn(new FirewallConfig('admin', 'user_checker'));

        // Mark the current firewall as disabled (adjust name to match your firewall configuration)
        $subscriber = new LocaleFilterConfigurator($this->entityManager, ['admin'], $firewallMap);

        $subscriber->onKernelRequest($event);

        $filters = $this->entityManager->getFilters();
        $this->assertFalse(
            $filters->isEnabled('tmi_translation_locale_filter'),
            'Filter should not be active for a disabled firewall'
        );
    }

    public function testFilterWorksWhenFirewallMapIsNull(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $request->setLocale('de');

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Subscriber with no firewall map simulates no firewall restrictions
        $subscriber = new LocaleFilterConfigurator($this->entityManager, [], null);
        $subscriber->onKernelRequest($event);

        $filter = $this->entityManager->getFilters()->getFilter('tmi_translation_locale_filter');
        $this->assertInstanceOf(LocaleFilter::class, $filter);
        $this->assertSame("'de'", $filter->getParameter('locale'));
    }

    public function testOnKernelRequestDoesNothingIfFilterNotRegistered(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Mock the EntityManager to return a Filters object that does NOT have our filter
        $filtersMock = $this->getMockBuilder(FilterCollection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $filtersMock->method('has')->willReturn(false);

        $emMock = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $emMock->method('getFilters')->willReturn($filtersMock);

        $subscriber = new LocaleFilterConfigurator($emMock, []);

        // Should execute early return without exception
        $subscriber->onKernelRequest($event);

        $this->assertTrue(true, 'Executed onKernelRequest with missing filter without errors');
    }
    /**
     * Test isDisabledFirewall returns false when FirewallMap returns null
     * @throws ReflectionException
     */
    public function testIsDisabledFirewallReturnsFalseWhenConfigIsNull(): void
    {
        $firewallMap = $this->createMock(FirewallMap::class);
        $firewallMap->method('getFirewallConfig')->willReturn(null);

        $subscriber = new LocaleFilterConfigurator($this->entityManager, ['admin'], $firewallMap);

        $request = new Request();
        $result = $this->invokePrivateMethod($subscriber, [$request]);

        $this->assertFalse($result, 'Expected isDisabledFirewall to return false if config is null');
    }

    /**
     * Helper to call private methods
     * @throws ReflectionException
     */
    private function invokePrivateMethod(object $object, array $args = []): mixed
    {
        $reflection = new ReflectionClass($object);
        return $reflection->getMethod('isDisabledFirewall')->invokeArgs($object, $args);
    }
}

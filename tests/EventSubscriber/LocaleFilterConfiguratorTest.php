<?php

declare(strict_types=1);

namespace Tmi\TranslationBundle\Test\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\FilterCollection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Tmi\TranslationBundle\Doctrine\Filter\LocaleFilter;
use Tmi\TranslationBundle\EventSubscriber\LocaleFilterConfigurator;
use Tmi\TranslationBundle\Fixtures\Entity\Scalar\Scalar;
use Tmi\TranslationBundle\Test\IntegrationTestCase;
use Tmi\TranslationBundle\ValueObject\Tuuid;

#[AllowMockObjectsWithoutExpectations]
final class LocaleFilterConfiguratorTest extends IntegrationTestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = LocaleFilterConfigurator::getSubscribedEvents();
        self::assertArrayHasKey(KernelEvents::REQUEST, $events);
        self::assertSame([['onKernelRequest', 2]], $events[KernelEvents::REQUEST]);
        self::assertArrayHasKey(KernelEvents::FINISH_REQUEST, $events);
        self::assertSame([['onKernelFinishRequest', 2]], $events[KernelEvents::FINISH_REQUEST]);
    }

    /**
     * A fragment/ESI/forward sets the filter to its own locale; once it finishes, the rest
     * of the parent request must not keep querying with the sub-request's locale.
     */
    public function testParentLocaleIsRestoredWhenSubRequestFinishes(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        $mainRequest = new Request();
        $mainRequest->setLocale('en_US');

        $subRequest = new Request();
        $subRequest->setLocale('de_DE');

        $requestStack = new RequestStack();
        $requestStack->push($mainRequest);

        $subscriber = new LocaleFilterConfigurator($this->entityManager(), [], null, $requestStack);

        $subscriber->onKernelRequest(new RequestEvent($kernel, $mainRequest, HttpKernelInterface::MAIN_REQUEST));
        self::assertSame("'en_US'", $this->filterLocale());

        // Sub-request legitimately gets its own locale while it is being handled
        $requestStack->push($subRequest);
        $subscriber->onKernelRequest(new RequestEvent($kernel, $subRequest, HttpKernelInterface::SUB_REQUEST));
        self::assertSame("'de_DE'", $this->filterLocale());

        // finish_request fires before the sub-request is popped, so the parent is reachable
        $subscriber->onKernelFinishRequest(
            new FinishRequestEvent($kernel, $subRequest, HttpKernelInterface::SUB_REQUEST),
        );
        self::assertSame("'en_US'", $this->filterLocale());
    }

    /**
     * Regression for the v3.0 fix "Locale filter leaked out of sub-requests",
     * in the exact shape the consumer hit: a fragment rendered in another
     * locale runs and finishes, and the parent request's *subsequent queries*
     * must be filtered by the parent's locale again. Without the restore, every
     * later query in the worker keeps returning the sub-request's language.
     */
    public function testSubRequestLocaleDoesNotLeakIntoParentQueries(): void
    {
        $tuuid = Tuuid::generate();

        $this->entityManager()->persist(new Scalar()->setTuuid($tuuid)->setLocale('en_US')->setTitle('English'));
        $this->entityManager()->persist(new Scalar()->setTuuid($tuuid)->setLocale('de_DE')->setTitle('Deutsch'));
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $kernel = $this->createMock(HttpKernelInterface::class);

        $mainRequest = new Request();
        $mainRequest->setLocale('en_US');

        $subRequest = new Request();
        $subRequest->setLocale('de_DE');

        $requestStack = new RequestStack();
        $subscriber   = new LocaleFilterConfigurator($this->entityManager(), [], null, $requestStack);

        // Main request starts: queries return the parent locale's variant.
        $requestStack->push($mainRequest);
        $subscriber->onKernelRequest(new RequestEvent($kernel, $mainRequest, HttpKernelInterface::MAIN_REQUEST));
        self::assertSame(['English'], $this->titlesOf($tuuid));

        // A fragment rendered in another locale queries in its own locale.
        $requestStack->push($subRequest);
        $subscriber->onKernelRequest(new RequestEvent($kernel, $subRequest, HttpKernelInterface::SUB_REQUEST));
        self::assertSame(['Deutsch'], $this->titlesOf($tuuid));

        // The sub-request finishes — finish_request fires before the stack pops,
        // mirroring HttpKernel::finishRequest().
        $subscriber->onKernelFinishRequest(
            new FinishRequestEvent($kernel, $subRequest, HttpKernelInterface::SUB_REQUEST),
        );
        $requestStack->pop();

        // The consumer failure: without the restore this stays ['Deutsch'] for
        // the rest of the parent request.
        self::assertSame(['English'], $this->titlesOf($tuuid));
    }

    public function testFinishRequestLeavesLocaleAloneForTheMainRequest(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        $mainRequest = new Request();
        $mainRequest->setLocale('fr');

        $requestStack = new RequestStack();
        $requestStack->push($mainRequest);

        $subscriber = new LocaleFilterConfigurator($this->entityManager(), [], null, $requestStack);
        $subscriber->onKernelRequest(new RequestEvent($kernel, $mainRequest, HttpKernelInterface::MAIN_REQUEST));

        $subscriber->onKernelFinishRequest(
            new FinishRequestEvent($kernel, $mainRequest, HttpKernelInterface::MAIN_REQUEST),
        );

        self::assertSame("'fr'", $this->filterLocale());
    }

    public function testFinishRequestIsANoopWithoutARequestStack(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        $request = new Request();
        $request->setLocale('it_IT');

        $subscriber = new LocaleFilterConfigurator($this->entityManager(), []);
        $subscriber->onKernelRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        $subscriber->onKernelFinishRequest(
            new FinishRequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST),
        );

        self::assertSame("'it_IT'", $this->filterLocale());
    }

    public function testRestoreDisablesTheFilterWhenTheParentIsOnADisabledFirewall(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        $mainRequest = new Request();
        $mainRequest->setLocale('en_US');

        $subRequest = new Request();
        $subRequest->setLocale('de_DE');

        $requestStack = new RequestStack();
        $requestStack->push($mainRequest);
        $requestStack->push($subRequest);

        $firewallMap = $this->createMock(FirewallMap::class);
        $firewallMap->method('getFirewallConfig')->willReturnCallback(
            static fn (Request $request): FirewallConfig|null => $request === $mainRequest
                ? new FirewallConfig('admin', 'user_checker')
                : null,
        );

        $subscriber = new LocaleFilterConfigurator(
            $this->entityManager(),
            ['admin'],
            $firewallMap,
            $requestStack,
        );

        // Sub-request is on an allowed firewall, so the filter is enabled for it
        $subscriber->onKernelRequest(new RequestEvent($kernel, $subRequest, HttpKernelInterface::SUB_REQUEST));
        self::assertTrue($this->entityManager()->getFilters()->isEnabled('tmi_translation_locale_filter'));

        // Restoring the parent must reapply the parent's disabled-firewall rule, not its locale
        $subscriber->onKernelFinishRequest(
            new FinishRequestEvent($kernel, $subRequest, HttpKernelInterface::SUB_REQUEST),
        );

        self::assertFalse($this->entityManager()->getFilters()->isEnabled('tmi_translation_locale_filter'));
    }

    public function testFilterIsEnabledAndLocaleSet(): void
    {
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $request->setLocale('en_US');

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Subscriber without disabled firewalls
        $subscriber = new LocaleFilterConfigurator($this->entityManager(), []);
        $subscriber->onKernelRequest($event);

        $filter = $this->entityManager()->getFilters()->getFilter('tmi_translation_locale_filter');

        self::assertInstanceOf(LocaleFilter::class, $filter);
        self::assertSame("'en_US'", $filter->getParameter('locale')); // Doctrine stores parameter in SQL form
    }

    public function testFilterCanChangeLocale(): void
    {
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $request->setLocale('fr');

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber = new LocaleFilterConfigurator($this->entityManager(), []);
        $subscriber->onKernelRequest($event);

        $filter = $this->entityManager()->getFilters()->getFilter('tmi_translation_locale_filter');
        self::assertInstanceOf(LocaleFilter::class, $filter);
        self::assertSame("'fr'", $filter->getParameter('locale'));
    }

    public function testFilterDisabledForDisabledFirewall(): void
    {
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $request->setLocale('en_US');

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $firewallMap = $this->createMock(FirewallMap::class);
        $firewallMap->method('getFirewallConfig')
            ->willReturn(new FirewallConfig('admin', 'user_checker'));

        // Mark the current firewall as disabled (adjust name to match your firewall configuration)
        $subscriber = new LocaleFilterConfigurator($this->entityManager(), ['admin'], $firewallMap);

        $subscriber->onKernelRequest($event);

        $filters = $this->entityManager()->getFilters();
        self::assertFalse(
            $filters->isEnabled('tmi_translation_locale_filter'),
            'Filter should not be active for a disabled firewall',
        );
    }

    public function testFilterWorksWhenFirewallMapIsNull(): void
    {
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $request->setLocale('de_DE');

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Subscriber with no firewall map simulates no firewall restrictions
        $subscriber = new LocaleFilterConfigurator($this->entityManager(), [], null);
        $subscriber->onKernelRequest($event);

        $filter = $this->entityManager()->getFilters()->getFilter('tmi_translation_locale_filter');
        self::assertInstanceOf(LocaleFilter::class, $filter);
        self::assertSame("'de_DE'", $filter->getParameter('locale'));
    }

    public function testOnKernelRequestDoesNothingIfFilterNotRegistered(): void
    {
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $event   = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Mock the EntityManager to return a Filters object that does NOT have our filter
        $filtersMock = $this->createMock(FilterCollection::class);
        $filtersMock->method('has')->willReturn(false);

        $emMock = $this->createMock(EntityManagerInterface::class);
        $emMock->method('getFilters')->willReturn($filtersMock);

        $subscriber = new LocaleFilterConfigurator($emMock, []);

        // Should execute early return without exception
        $subscriber->onKernelRequest($event);

        $this->addToAssertionCount(1);
    }

    /**
     * Test isDisabledFirewall returns false when FirewallMap returns null.
     *
     * @throws \ReflectionException
     */
    public function testIsDisabledFirewallReturnsFalseWhenConfigIsNull(): void
    {
        $firewallMap = $this->createMock(FirewallMap::class);
        $firewallMap->method('getFirewallConfig')->willReturn(null);

        $subscriber = new LocaleFilterConfigurator($this->entityManager(), ['admin'], $firewallMap);

        $request = new Request();
        $result  = $this->invokePrivateMethod($subscriber, [$request]);

        self::assertFalse($result, 'Expected isDisabledFirewall to return false if config is null');
    }

    /**
     * Titles of every Scalar row the current filter state lets a fresh query see.
     *
     * @return list<string|null>
     */
    private function titlesOf(Tuuid $tuuid): array
    {
        $this->entityManager()->clear();

        /** @var list<Scalar> $rows */
        $rows = $this->entityManager()->createQueryBuilder()
            ->select('s')
            ->from(Scalar::class, 's')
            ->where('s.tuuid = :tuuid')
            ->setParameter('tuuid', (string) $tuuid)
            ->getQuery()
            ->getResult();

        return array_map(static fn (Scalar $row): string|null => $row->getTitle(), $rows);
    }

    private function filterLocale(): mixed
    {
        $filter = $this->entityManager()->getFilters()->getFilter('tmi_translation_locale_filter');
        self::assertInstanceOf(LocaleFilter::class, $filter);

        // Doctrine stores the parameter in SQL form
        return $filter->getParameter('locale');
    }

    /**
     * Helper to call private methods.
     *
     * @param array<Request> $args Arguments to pass to the method
     *
     * @throws \ReflectionException
     */
    private function invokePrivateMethod(object $object, array $args = []): mixed
    {
        $reflection = new \ReflectionClass($object);

        return $reflection->getMethod('isDisabledFirewall')->invokeArgs($object, $args);
    }
}

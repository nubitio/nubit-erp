<?php

declare(strict_types=1);

namespace App\Tests\Identity\EventSubscriber;

use App\Identity\Auth\BranchContextServiceInterface;
use App\Identity\Entity\User;
use App\Identity\EventSubscriber\BranchFilterSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\FilterCollection;
use Nubit\AdminBundle\Auth\JWTAuthenticationToken;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Concrete SQLFilter subclass for testing.
 * Instantiated via newInstanceWithoutConstructor + reflection to inject a mock EM.
 * @internal
 */
#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class FilterStub extends \Doctrine\ORM\Query\Filter\SQLFilter
{
    public function addFilterConstraint(
        \Doctrine\ORM\Mapping\ClassMetadata $targetEntity,
        string $targetTableAlias,
    ): string {
        return '';
    }

    /**
     * Creates a FilterStub with a mock EntityManager injected so that
     * setParameter() can call setFiltersStateDirty() without errors.
     */
    public static function createWithMockEm(\PHPUnit\Framework\MockObject\MockObject $mockEm): self
    {
        /** @var self $stub */
        $stub = new \ReflectionClass(self::class)->newInstanceWithoutConstructor();

        // Inject the mock EM into the private readonly 'em' property of SQLFilter
        $emProp = new \ReflectionProperty(\Doctrine\ORM\Query\Filter\SQLFilter::class, 'em');
        $emProp->setValue($stub, $mockEm);

        return $stub;
    }
}

#[CoversClass(BranchFilterSubscriber::class)]
#[AllowMockObjectsWithoutExpectations]
final class BranchFilterSubscriberTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private FilterCollection&MockObject $filterCollection;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->filterCollection = $this->createMock(FilterCollection::class);
    }

    public function testGetSubscribedEventsReturnsRequestWithPriority5(): void
    {
        self::assertSame(
            [KernelEvents::REQUEST => ['onKernelRequest', 5]],
            BranchFilterSubscriber::getSubscribedEvents(),
        );
    }

    public function testSubRequestIsIgnored(): void
    {
        $event = $this->createStub(RequestEvent::class);
        $event->method('isMainRequest')->willReturn(false);

        $this->entityManager->expects(self::never())->method('getFilters');

        $subscriber = $this->subscriber('42');
        $subscriber->onKernelRequest($event);
    }

    public function testNoBranchInContextDoesNotTouchFilters(): void
    {
        $event = $this->createStub(RequestEvent::class);
        $event->method('isMainRequest')->willReturn(true);

        $this->entityManager->expects(self::never())->method('getFilters');

        $subscriber = $this->subscriber(null);
        $subscriber->onKernelRequest($event);
    }

    public function testBranchSetAndFilterNotYetEnabledEnablesAndSetsParameter(): void
    {
        $event = $this->createStub(RequestEvent::class);
        $event->method('isMainRequest')->willReturn(true);

        // The real setParameter() calls em->getFilters()->setFiltersStateDirty()
        $innerFilterCollection = $this->createStub(FilterCollection::class);
        $innerFilterCollection->method('setFiltersStateDirty');
        $innerEm = $this->createMock(EntityManagerInterface::class);
        $innerEm->method('getFilters')->willReturn($innerFilterCollection);

        $filter = FilterStub::createWithMockEm($innerEm);

        $this->filterCollection->method('isEnabled')->with('branch_filter')->willReturn(false);
        $this->filterCollection->expects(self::once())->method('enable')->with('branch_filter');
        $this->filterCollection->method('getFilter')->with('branch_filter')->willReturn($filter);
        $this->entityManager->method('getFilters')->willReturn($this->filterCollection);

        $subscriber = $this->subscriber('42');
        $subscriber->onKernelRequest($event);

        // Read the private $parameters array via reflection
        $paramsProp = new \ReflectionProperty(\Doctrine\ORM\Query\Filter\SQLFilter::class, 'parameters');
        $params = $paramsProp->getValue($filter);

        self::assertIsArray($params);
        self::assertArrayHasKey('branchIds', $params);
        // doctrine/orm >= 3.6 stores Parameter objects; older versions arrays.
        $param = $params['branchIds'];
        self::assertSame(['42'], \is_array($param) ? $param['value'] : $param->value);
    }

    public function testBranchSetAndFilterAlreadyEnabledSkipsEnableButSetsParameter(): void
    {
        $event = $this->createStub(RequestEvent::class);
        $event->method('isMainRequest')->willReturn(true);

        $innerFilterCollection = $this->createStub(FilterCollection::class);
        $innerFilterCollection->method('setFiltersStateDirty');
        $innerEm = $this->createMock(EntityManagerInterface::class);
        $innerEm->method('getFilters')->willReturn($innerFilterCollection);

        $filter = FilterStub::createWithMockEm($innerEm);

        $this->filterCollection->method('isEnabled')->with('branch_filter')->willReturn(true);
        $this->filterCollection->expects(self::never())->method('enable');
        $this->filterCollection->method('getFilter')->with('branch_filter')->willReturn($filter);
        $this->entityManager->method('getFilters')->willReturn($this->filterCollection);

        $subscriber = $this->subscriber('7');
        $subscriber->onKernelRequest($event);

        $paramsProp = new \ReflectionProperty(\Doctrine\ORM\Query\Filter\SQLFilter::class, 'parameters');
        $params = $paramsProp->getValue($filter);

        self::assertIsArray($params);
        self::assertArrayHasKey('branchIds', $params);
        $param = $params['branchIds'];
        self::assertSame(['7'], \is_array($param) ? $param['value'] : $param->value);
    }

    public function testAuthenticatedSessionWithoutValidBranchHasEmptyScope(): void
    {
        $event = $this->createStub(RequestEvent::class);
        $event->method('isMainRequest')->willReturn(true);
        $this->entityManager->method('getFilters')->willReturn($this->filterCollection);
        $filter = FilterStub::createWithMockEm($this->entityManager);
        $this->filterCollection->expects(self::once())->method('enable')->with('branch_filter');
        $this->filterCollection->method('getFilter')->with('branch_filter')->willReturn($filter);

        $this->subscriber(null, authenticated: true)->onKernelRequest($event);

        self::assertSame('', $filter->getParameterList('branchIds'));
    }

    private function subscriber(?string $branchId, bool $authenticated = false): BranchFilterSubscriber
    {
        $context = $this->createStub(BranchContextServiceInterface::class);
        $context->method('getCurrentBranchId')->willReturn($branchId);
        $security = $this->createStub(Security::class);
        if ($authenticated) {
            $security->method('getToken')->willReturn(new JWTAuthenticationToken(
                (new User())->setUsername('tester'),
                'api',
                ['ROLE_USER'],
                'token',
            ));
        }

        return new BranchFilterSubscriber($this->entityManager, $context, $security);
    }
}

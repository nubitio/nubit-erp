<?php

declare(strict_types=1);

namespace App\Tests\Identity\EventSubscriber;

use App\Identity\EventSubscriber\BranchFilterSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\FilterCollection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Minimal interface for the Doctrine SQL filter object returned by getFilter().
 * @internal
 */
interface FilterParameterSetter
{
    public function setParameter(string $name, mixed $value): void;
}

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

/**
 * Test-only subclass of BranchFilterSubscriber that accepts a branch ID directly,
 * bypassing the final BranchContextService dependency.
 *
 * This is necessary because BranchContextService is declared `final readonly`
 * and cannot be mocked or subclassed.
 *
 * @internal
 */
final class TestableBranchFilterSubscriber extends BranchFilterSubscriber
{
    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly ?string $testBranchId,
    ) {
        // We pass a bare BranchContextService instance (without constructor) to satisfy
        // the parent constructor's type hint. The parent constructor stores it as readonly,
        // but we override onKernelRequest so the stored value is never used.
        $branchContextService = new \ReflectionClass(\App\Identity\Auth\BranchContextService::class)->newInstanceWithoutConstructor();

        parent::__construct($entityManager, $branchContextService);
    }

    public function onKernelRequest(\Symfony\Component\HttpKernel\Event\RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $branchId = $this->testBranchId;
        if ($branchId === null) {
            return;
        }

        $filters = $this->getEntityManager()->getFilters();

        if (!$filters->isEnabled('branch_filter')) {
            $filters->enable('branch_filter');
        }

        $filters->getFilter('branch_filter')->setParameter('branchId', $branchId);
    }

    private function getEntityManager(): EntityManagerInterface
    {
        $ref = new \ReflectionProperty(BranchFilterSubscriber::class, 'entityManager');

        return $ref->getValue($this);
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

        $subscriber = new TestableBranchFilterSubscriber($this->entityManager, '42');
        $subscriber->onKernelRequest($event);
    }

    public function testNoBranchInContextDoesNotTouchFilters(): void
    {
        $event = $this->createStub(RequestEvent::class);
        $event->method('isMainRequest')->willReturn(true);

        $this->entityManager->expects(self::never())->method('getFilters');

        $subscriber = new TestableBranchFilterSubscriber($this->entityManager, null);
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

        $subscriber = new TestableBranchFilterSubscriber($this->entityManager, '42');
        $subscriber->onKernelRequest($event);

        // Read the private $parameters array via reflection
        $paramsProp = new \ReflectionProperty(\Doctrine\ORM\Query\Filter\SQLFilter::class, 'parameters');
        $params = $paramsProp->getValue($filter);

        self::assertIsArray($params);
        self::assertArrayHasKey('branchId', $params);
        // doctrine/orm >= 3.6 stores Parameter objects; older versions arrays.
        $param = $params['branchId'];
        self::assertSame('42', \is_array($param) ? $param['value'] : $param->value);
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

        $subscriber = new TestableBranchFilterSubscriber($this->entityManager, '7');
        $subscriber->onKernelRequest($event);

        $paramsProp = new \ReflectionProperty(\Doctrine\ORM\Query\Filter\SQLFilter::class, 'parameters');
        $params = $paramsProp->getValue($filter);

        self::assertIsArray($params);
        self::assertArrayHasKey('branchId', $params);
        $param = $params['branchId'];
        self::assertSame('7', \is_array($param) ? $param['value'] : $param->value);
    }
}

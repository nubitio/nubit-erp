<?php

declare(strict_types=1);

namespace App\Tests\Purchasing\State;

use App\Identity\Auth\OperatingBranchResolver;
use App\Identity\Entity\User;
use App\Organization\Repository\BranchRepository;
use App\Purchasing\Entity\PurchaseOrder;
use App\Purchasing\State\PurchasingBranchScope;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class PurchasingBranchScopeTest extends TestCase
{
    public function testReadOnlyBranchCannotBeUsedForCentralPurchasing(): void
    {
        $user = new User();
        $user->assignOperatingBranches(['local'], 'local');
        $user->assignReadOnlyBranches(['other']);
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturn(true);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getFilters');

        $request = Request::create('/api/purchase-orders?branchId=other', 'POST');
        $request->attributes->set('_api_resource_class', PurchaseOrder::class);
        $requests = new RequestStack();
        $requests->push($request);
        $scope = new PurchasingBranchScope(
            $requests,
            $security,
            new OperatingBranchResolver($this->createStub(BranchRepository::class)),
            $entityManager,
        );
        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $this->expectException(AccessDeniedHttpException::class);
        $scope->apply($event);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Identity\Auth;

use App\Identity\Auth\BranchContextServiceInterface;
use App\Identity\Auth\BranchReportScope;
use App\Identity\Entity\User;
use App\Organization\Entity\Branch;
use App\Organization\Repository\BranchRepository;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class BranchReportScopeTest extends TestCase
{
    public function testReadAssignmentIncludesHistoricalBranchWithoutGrantingOperations(): void
    {
        $user = new User();
        $user->assignOperatingBranches(['current'], 'current');
        $user->assignReadOnlyBranches(['closed']);
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);
        $context = $this->createStub(BranchContextServiceInterface::class);
        $context->method('getCurrentBranchId')->willReturn('current');
        $historical = new Branch()->setIsActive(false);
        new ReflectionProperty(Branch::class, 'id')->setValue($historical, 'closed');
        $branches = $this->createMock(BranchRepository::class);
        $branches
            ->expects(self::exactly(2))
            ->method('findBy')
            ->with(['id' => ['current', 'closed']], ['name' => 'ASC'])
            ->willReturn([$historical]);
        $scope = new BranchReportScope($security, $context, $branches);

        self::assertSame('closed', $scope->requireBranchId('closed'));
        self::assertFalse($user->canOperateInBranch('closed'));

        $this->expectException(AccessDeniedHttpException::class);
        $scope->requireBranchId('unassigned');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Payroll\State;

use ApiPlatform\Metadata\Post;
use App\Identity\Dto\AuthUserDto;
use App\Identity\Security\Security;
use App\Payroll\Dto\PayrollRunApproveDto;
use App\Payroll\Entity\PayrollRun;
use App\Payroll\Service\PayrollRunService;
use App\Payroll\State\PayrollRunApproveProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Exception\ValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class PayrollRunApproveProcessorTest extends TestCase
{
    public function testApprovesCalculatedRun(): void
    {
        $run = new PayrollRun()->setStatus(PayrollRun::CALCULATED);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->with(PayrollRun::class, 'r1')->willReturn($run);
        $entityManager->expects(self::once())->method('flush');

        $security = $this->createMock(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(userId: 7, username: 'boss'));

        $processor = new PayrollRunApproveProcessor($entityManager, $security, new PayrollRunService());
        $result = $processor->process(new PayrollRunApproveDto(id: 'r1'), new Post());

        static::assertSame(PayrollRun::APPROVED, $result->getStatus());
        static::assertNotNull($result->getApprovedAt());
    }

    public function testThrowsWhenRunIsStillDraft(): void
    {
        $run = new PayrollRun();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn($run);
        $entityManager->expects(self::never())->method('flush');

        $security = $this->createMock(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(userId: 7, username: 'boss'));

        $processor = new PayrollRunApproveProcessor($entityManager, $security, new PayrollRunService());

        $this->expectException(ValidationException::class);
        $processor->process(new PayrollRunApproveDto(id: 'r1'), new Post());
    }

    public function testThrowsWhenNotAuthenticated(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('find');

        $security = $this->createMock(Security::class);
        $security->method('connectedUser')->willReturn(null);

        $processor = new PayrollRunApproveProcessor($entityManager, $security, new PayrollRunService());

        $this->expectException(UnauthorizedHttpException::class);
        $processor->process(new PayrollRunApproveDto(id: 'r1'), new Post());
    }

    public function testThrowsWhenRunNotFound(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn(null);

        $security = $this->createMock(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(userId: 7, username: 'boss'));

        $processor = new PayrollRunApproveProcessor($entityManager, $security, new PayrollRunService());

        $this->expectException(NotFoundHttpException::class);
        $processor->process(new PayrollRunApproveDto(id: 'missing'), new Post());
    }
}

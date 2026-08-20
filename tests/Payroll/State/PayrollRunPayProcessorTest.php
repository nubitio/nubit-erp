<?php

declare(strict_types=1);

namespace App\Tests\Payroll\State;

use ApiPlatform\Metadata\Post;
use App\Identity\Dto\AuthUserDto;
use App\Identity\Security\Security;
use App\Payroll\Dto\PayrollRunPayDto;
use App\Payroll\Entity\PayrollRun;
use App\Payroll\Service\PayrollRunService;
use App\Payroll\State\PayrollRunPayProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Exception\ValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class PayrollRunPayProcessorTest extends TestCase
{
    public function testPaysApprovedRun(): void
    {
        $run = new PayrollRun()->setStatus(PayrollRun::APPROVED);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->with(PayrollRun::class, 'r1')->willReturn($run);
        $entityManager->expects(self::once())->method('flush');

        $security = $this->createMock(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(userId: 7, username: 'boss'));

        $processor = new PayrollRunPayProcessor($entityManager, $security, new PayrollRunService());
        $result = $processor->process(new PayrollRunPayDto(id: 'r1'), new Post());

        static::assertSame(PayrollRun::PAID, $result->getStatus());
        static::assertNotNull($result->getPaidAt());
    }

    public function testThrowsWhenRunIsStillCalculated(): void
    {
        $run = new PayrollRun()->setStatus(PayrollRun::CALCULATED);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn($run);
        $entityManager->expects(self::never())->method('flush');

        $security = $this->createMock(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(userId: 7, username: 'boss'));

        $processor = new PayrollRunPayProcessor($entityManager, $security, new PayrollRunService());

        $this->expectException(ValidationException::class);
        $processor->process(new PayrollRunPayDto(id: 'r1'), new Post());
    }

    public function testThrowsWhenNotAuthenticated(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('find');

        $security = $this->createMock(Security::class);
        $security->method('connectedUser')->willReturn(null);

        $processor = new PayrollRunPayProcessor($entityManager, $security, new PayrollRunService());

        $this->expectException(UnauthorizedHttpException::class);
        $processor->process(new PayrollRunPayDto(id: 'r1'), new Post());
    }

    public function testThrowsWhenRunNotFound(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn(null);

        $security = $this->createMock(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(userId: 7, username: 'boss'));

        $processor = new PayrollRunPayProcessor($entityManager, $security, new PayrollRunService());

        $this->expectException(NotFoundHttpException::class);
        $processor->process(new PayrollRunPayDto(id: 'missing'), new Post());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\ExpenseReports\State;

use ApiPlatform\Metadata\Post;
use App\ExpenseReports\Dto\ExpenseReportReimburseDto;
use App\ExpenseReports\Entity\ExpenseReport;
use App\ExpenseReports\Service\ExpenseReportService;
use App\ExpenseReports\State\ExpenseReportReimburseProcessor;
use App\Identity\Dto\AuthUserDto;
use App\Identity\Security\Security;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Exception\ValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class ExpenseReportReimburseProcessorTest extends TestCase
{
    public function testReimbursesApprovedReport(): void
    {
        $report = new ExpenseReport()->setStatus(ExpenseReport::APPROVED);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->with(ExpenseReport::class, 'r1')->willReturn($report);
        $entityManager->expects(self::once())->method('flush');

        $security = $this->createMock(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(userId: 7, username: 'boss'));

        $processor = new ExpenseReportReimburseProcessor($entityManager, $security, new ExpenseReportService());
        $result = $processor->process(new ExpenseReportReimburseDto(id: 'r1'), new Post());

        static::assertSame(ExpenseReport::REIMBURSED, $result->getStatus());
        static::assertNotNull($result->getReimbursedAt());
    }

    public function testThrowsWhenReportIsStillPending(): void
    {
        $report = new ExpenseReport();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn($report);
        $entityManager->expects(self::never())->method('flush');

        $security = $this->createMock(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(userId: 7, username: 'boss'));

        $processor = new ExpenseReportReimburseProcessor($entityManager, $security, new ExpenseReportService());

        $this->expectException(ValidationException::class);
        $processor->process(new ExpenseReportReimburseDto(id: 'r1'), new Post());
    }

    public function testThrowsWhenNotAuthenticated(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('find');

        $security = $this->createMock(Security::class);
        $security->method('connectedUser')->willReturn(null);

        $processor = new ExpenseReportReimburseProcessor($entityManager, $security, new ExpenseReportService());

        $this->expectException(UnauthorizedHttpException::class);
        $processor->process(new ExpenseReportReimburseDto(id: 'r1'), new Post());
    }

    public function testThrowsWhenReportNotFound(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn(null);

        $security = $this->createMock(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(userId: 7, username: 'boss'));

        $processor = new ExpenseReportReimburseProcessor($entityManager, $security, new ExpenseReportService());

        $this->expectException(NotFoundHttpException::class);
        $processor->process(new ExpenseReportReimburseDto(id: 'missing'), new Post());
    }
}

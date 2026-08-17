<?php

declare(strict_types=1);

namespace App\Tests\ExpenseReports\State;

use ApiPlatform\Metadata\Post;
use App\ExpenseReports\Dto\ExpenseReportReviewDto;
use App\ExpenseReports\Entity\ExpenseReport;
use App\ExpenseReports\Service\ExpenseReportService;
use App\ExpenseReports\State\ExpenseReportApproveProcessor;
use App\Identity\Dto\AuthUserDto;
use App\Identity\Entity\User;
use App\Identity\Security\Security;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class ExpenseReportApproveProcessorTest extends TestCase
{
    public function testApprovesPendingReportAndRecordsReviewer(): void
    {
        $report = new ExpenseReport();
        $reviewer = new User();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->with(ExpenseReport::class, 'r1')->willReturn($report);
        $entityManager->method('getReference')->with(User::class, 7)->willReturn($reviewer);
        $entityManager->expects(self::once())->method('flush');

        $security = $this->createMock(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(userId: 7, username: 'boss'));

        $processor = new ExpenseReportApproveProcessor($entityManager, $security, new ExpenseReportService());
        $result = $processor->process(new ExpenseReportReviewDto(id: 'r1', note: 'Todo en orden'), new Post());

        static::assertSame(ExpenseReport::APPROVED, $result->getStatus());
        static::assertSame($reviewer, $result->getReviewedBy());
        static::assertNotNull($result->getReviewedAt());
        static::assertSame('Todo en orden', $result->getReviewNote());
    }

    public function testThrowsWhenNotAuthenticated(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('find');

        $security = $this->createMock(Security::class);
        $security->method('connectedUser')->willReturn(null);

        $processor = new ExpenseReportApproveProcessor($entityManager, $security, new ExpenseReportService());

        $this->expectException(UnauthorizedHttpException::class);
        $processor->process(new ExpenseReportReviewDto(id: 'r1'), new Post());
    }

    public function testThrowsWhenReportNotFound(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn(null);
        $entityManager->expects(self::never())->method('flush');

        $security = $this->createMock(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(userId: 7, username: 'boss'));

        $processor = new ExpenseReportApproveProcessor($entityManager, $security, new ExpenseReportService());

        $this->expectException(NotFoundHttpException::class);
        $processor->process(new ExpenseReportReviewDto(id: 'missing'), new Post());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\ExpenseReports\State;

use ApiPlatform\Metadata\Post;
use App\ExpenseReports\Dto\ExpenseReportReviewDto;
use App\ExpenseReports\Entity\ExpenseReport;
use App\ExpenseReports\Service\ExpenseReportService;
use App\ExpenseReports\State\ExpenseReportRejectProcessor;
use App\Identity\Dto\AuthUserDto;
use App\Identity\Entity\User;
use App\Identity\Security\Security;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ExpenseReportRejectProcessorTest extends TestCase
{
    public function testRejectsPendingReportAndRecordsReviewer(): void
    {
        $report = new ExpenseReport();
        $reviewer = new User();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->with(ExpenseReport::class, 'r1')->willReturn($report);
        $entityManager->method('getReference')->with(User::class, 7)->willReturn($reviewer);
        $entityManager->expects(self::once())->method('flush');

        $security = $this->createMock(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(userId: 7, username: 'boss'));

        $processor = new ExpenseReportRejectProcessor($entityManager, $security, new ExpenseReportService());
        $result = $processor->process(new ExpenseReportReviewDto(id: 'r1', note: 'Falta comprobante'), new Post());

        static::assertSame(ExpenseReport::REJECTED, $result->getStatus());
        static::assertSame($reviewer, $result->getReviewedBy());
        static::assertSame('Falta comprobante', $result->getReviewNote());
    }

    public function testThrowsWhenReportNotFound(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn(null);
        $entityManager->expects(self::never())->method('flush');

        $security = $this->createMock(Security::class);
        $security->method('connectedUser')->willReturn(new AuthUserDto(userId: 7, username: 'boss'));

        $processor = new ExpenseReportRejectProcessor($entityManager, $security, new ExpenseReportService());

        $this->expectException(NotFoundHttpException::class);
        $processor->process(new ExpenseReportReviewDto(id: 'missing'), new Post());
    }
}

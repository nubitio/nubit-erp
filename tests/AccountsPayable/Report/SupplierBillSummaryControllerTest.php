<?php

declare(strict_types=1);

namespace App\Tests\AccountsPayable\Report;

use App\AccountsPayable\Controller\SupplierBillSummaryController;
use App\AccountsPayable\Report\SupplierBillSummaryQuery;
use App\Identity\Auth\BranchContextServiceInterface;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class SupplierBillSummaryControllerTest extends TestCase
{
    public function testMissingBranchIsNotReportedAsZeroDebt(): void
    {
        $branch = $this->createMock(BranchContextServiceInterface::class);
        $branch->expects(self::once())->method('getCurrentBranchId')->willReturn(null);
        $query = new SupplierBillSummaryQuery(DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]));
        $controller = new SupplierBillSummaryController($query, $branch);
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Selecciona una sucursal');
        $controller();
    }
}

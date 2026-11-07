<?php

declare(strict_types=1);

namespace App\Tests\AccountsPayable\Report;

use App\AccountsPayable\Controller\PayablesAgingController;
use App\AccountsPayable\Report\PayablesAgingCalculator;
use App\AccountsPayable\Report\PayablesAgingQuery;
use App\Identity\Auth\BranchContextServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Export\XlsExporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class PayablesAgingControllerTest extends TestCase
{
    public function testMissingBranchIsNotReportedAsZeroDebt(): void
    {
        $controller = $this->controller(branchId: null);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Selecciona una sucursal');
        $controller->summary(new Request());
    }

    public function testRejectsPastCutOffDate(): void
    {
        $controller = $this->controller(branchId: '1');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('no puede ser anterior a hoy');
        $controller->summary(new Request(['as_of' => '2020-01-01']));
    }

    public function testRejectsMalformedCutOffDate(): void
    {
        $controller = $this->controller(branchId: '1');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Fecha inválida');
        $controller->summary(new Request(['as_of' => '06/11/2026']));
    }

    public function testExportRejectsUnknownView(): void
    {
        $controller = $this->controller(branchId: '1');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('summary');
        $controller->export(new Request(['view' => 'everything']));
    }

    private function controller(?string $branchId): PayablesAgingController
    {
        $branch = $this->createMock(BranchContextServiceInterface::class);
        $branch->method('getCurrentBranchId')->willReturn($branchId);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $controller = new PayablesAgingController(
            new PayablesAgingQuery($entityManager, new PayablesAgingCalculator()),
            $branch,
            $this->createStub(XlsExporter::class),
        );
        $controller->setContainer(new Container());

        return $controller;
    }
}

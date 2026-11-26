<?php

declare(strict_types=1);

namespace App\Tests\AccountsPayable\Report;

use App\AccountsPayable\Controller\PayablesAgingController;
use App\AccountsPayable\Report\PayablesAgingCalculator;
use App\AccountsPayable\Report\PayablesAgingQuery;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Identity\Auth\BranchReportScope;
use App\Identity\Entity\User;
use App\Organization\Repository\BranchRepository;
use Doctrine\DBAL\Connection;
use Nubit\Platform\Export\XlsExporter;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
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

    public function testExportRejectsUnassignedBranchBeforeQuerying(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->controller('current')->export(new Request(['branchId' => 'other']));
    }

    private function controller(?string $branchId): PayablesAgingController
    {
        $branch = $this->createMock(BranchContextServiceInterface::class);
        $branch->method('getCurrentBranchId')->willReturn($branchId);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(new User());
        $branches = $this->createStub(BranchRepository::class);
        $branches->method('findBy')->willReturn([]);
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('fetchAllAssociative');

        $controller = new PayablesAgingController(
            new PayablesAgingQuery($connection, new PayablesAgingCalculator()),
            new BranchReportScope($security, $branch, $branches),
            $this->createStub(XlsExporter::class),
        );
        $controller->setContainer(new Container());

        return $controller;
    }
}

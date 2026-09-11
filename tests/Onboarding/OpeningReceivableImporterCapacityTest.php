<?php

declare(strict_types=1);

namespace App\Tests\Onboarding;

use App\Catalog\Repository\PaymentMethodRepository;
use App\Contacts\Repository\CustomerRepository;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Inventory\Repository\WarehouseRepository;
use App\Onboarding\OpeningReceivableImporter;
use App\Organization\Repository\BranchRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use RuntimeException;

final class OpeningReceivableImporterCapacityTest extends TestCase
{
    public function testOversizedImportIsRejectedBeforeResolvingTenantState(): void
    {
        $importer = new OpeningReceivableImporter(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(CustomerRepository::class),
            $this->createMock(BranchRepository::class),
            $this->createMock(WarehouseRepository::class),
            $this->createMock(PaymentMethodRepository::class),
            $this->createMock(BranchContextServiceInterface::class),
            $this->createMock(Security::class),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('5000');

        $importer->import(array_fill(0, OpeningReceivableImporter::MAX_ROWS + 1, []));
    }

    public function testBudgetsArePositiveAndDocumentedInCode(): void
    {
        self::assertSame(5_000, OpeningReceivableImporter::MAX_ROWS);
        self::assertSame(250, OpeningReceivableImporter::FLUSH_EVERY);
    }
}

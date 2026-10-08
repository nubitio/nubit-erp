<?php

declare(strict_types=1);

namespace App\Tests\Onboarding;

use App\Onboarding\OpeningReceivableChargeToRecord;
use App\Onboarding\OpeningReceivableRowImporter;
use App\Onboarding\OpeningReceivableRowSkipped;
use App\Onboarding\OpeningReceivableTenantContext;
use App\Sales\Repository\SaleRecordRepository;
use App\Settings\Service\TenantSettingsService;
use App\Tests\ReleaseCritical\Support\CoreSaleFixture;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OpeningReceivableBranchDeduplicationTest extends KernelTestCase
{
    public function testReimportFromAnotherBranchDoesNotCreateTheSameDebtAgain(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $fixture = new CoreSaleFixture($entityManager, $container->get(TenantSettingsService::class));
        $original = $fixture->seed();
        $otherBranch = $fixture->seed();
        $row = [
            'ruc' => $original->customer->getRuc(),
            'nombre' => $original->customer->getBusinessName(),
            'monto' => '716.30',
            'vencimiento' => '2026-09-30',
            'fecha de emision' => '2026-06-15',
            'numero' => 'F001-' . bin2hex(random_bytes(4)),
        ];
        $importer = $container->get(OpeningReceivableRowImporter::class);
        $now = new DateTimeImmutable();
        $first = $importer->import(
            $row,
            new OpeningReceivableTenantContext($original->branch, $original->warehouse, $original->creditMethod),
            (int) $original->user->getId(),
            $now,
        );
        static::assertInstanceOf(OpeningReceivableChargeToRecord::class, $first);
        $entityManager->flush();

        $filters = $entityManager->getFilters();
        $filters->enable('branch_filter')->setParameter('branchId', $otherBranch->branch->getId());
        try {
            $key = $importer->retryKeyFor($row);
            static::assertNotNull($key);
            $repository = $container->get(SaleRecordRepository::class);
            static::assertNull(
                $repository->findByOfflineRetryKey($key),
                'The original sale is hidden by branch scope.',
            );

            $repeated = $importer->import(
                $row,
                new OpeningReceivableTenantContext(
                    $otherBranch->branch,
                    $otherBranch->warehouse,
                    $otherBranch->creditMethod,
                ),
                (int) $otherBranch->user->getId(),
                $now,
            );
            static::assertInstanceOf(OpeningReceivableRowSkipped::class, $repeated);
            $entityManager->flush();
            static::assertSame(
                1,
                (int) $entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM sales WHERE offline_retry_key = :key', [
                    'key' => $key,
                ]),
            );
        } finally {
            $filters->disable('branch_filter');
        }
    }
}

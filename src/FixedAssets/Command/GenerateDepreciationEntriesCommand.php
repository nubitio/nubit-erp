<?php

declare(strict_types=1);

namespace App\FixedAssets\Command;

use App\FixedAssets\Entity\AssetDepreciationEntry;
use App\FixedAssets\Entity\FixedAsset;
use App\FixedAssets\Service\FixedAssetService;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Generates straight-line depreciation entries for every ACTIVE FixedAsset,
 * one per calendar month elapsed since the last entry (or the purchase
 * month, if none yet), through the current month inclusive.
 *
 * Schedule: monthly, day 1, via Symfony Scheduler (see Schedule.php).
 *
 * Usage:
 *   php bin/console app:accounting:generate-depreciation-entries              # all tenants
 *   php bin/console app:accounting:generate-depreciation-entries --tenant=foo # one tenant
 */
#[AsCommand(
    name: 'app:accounting:generate-depreciation-entries',
    description: 'Generate due straight-line depreciation entries for active fixed assets',
)]
final class GenerateDepreciationEntriesCommand extends PerTenantCommand
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FixedAssetService $fixedAssetService,
    ) {
        parent::__construct();
    }

    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenantName = (string) ($this->currentTenantName ?? 'default');

        try {
            $today = new DateTimeImmutable('today', new DateTimeZone('America/Lima'));

            /** @var list<FixedAsset> $activeAssets */
            $activeAssets = $this->entityManager
                ->getRepository(FixedAsset::class)
                ->createQueryBuilder('fa')
                ->andWhere('fa.status = :status')
                ->setParameter('status', FixedAsset::ACTIVE)
                ->getQuery()
                ->getResult();

            if ($activeAssets === []) {
                $io->text(sprintf('[%s] No active fixed assets.', $tenantName));

                return self::SUCCESS;
            }

            $entryRepository = $this->entityManager->getRepository(AssetDepreciationEntry::class);
            $generated = 0;

            foreach ($activeAssets as $fixedAsset) {
                /** @var AssetDepreciationEntry|null $lastEntry */
                $lastEntry = $entryRepository
                    ->createQueryBuilder('e')
                    ->andWhere('e.fixedAsset = :fixedAsset')
                    ->setParameter('fixedAsset', $fixedAsset)
                    ->orderBy('e.period', 'DESC')
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();

                $newEntries = $this->fixedAssetService->generateEntriesThrough(
                    $fixedAsset,
                    $lastEntry?->getPeriod(),
                    $today,
                );

                foreach ($newEntries as $entry) {
                    $this->entityManager->persist($entry);
                    ++$generated;
                }

                if ($newEntries !== []) {
                    $this->entityManager->flush();
                }
            }

            $io->success(sprintf('[%s] %d depreciation entry(ies) generated.', $tenantName, $generated));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logger->error('GenerateDepreciationEntriesCommand: failed for tenant', [
                'tenant' => $tenantName,
                'exception' => $e->getMessage(),
            ]);
            $io->error(sprintf('[ERROR] Failed for %s: %s', $tenantName, $e->getMessage()));

            return self::FAILURE;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\FixedAssets\Command;

use App\FixedAssets\Command\GenerateDepreciationEntriesCommand;
use App\FixedAssets\Entity\FixedAsset;
use App\FixedAssets\Service\FixedAssetService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Integration test for GenerateDepreciationEntriesCommand: real
 * EntityManager against the demo_test DB, each test wrapped in a
 * rolled-back transaction (DAMA\DoctrineTestBundle). Calls
 * executeTenantCommand() directly via reflection, mirroring
 * GenerateRecurringSalesCommandTest, to exercise the persistence logic
 * without PerTenantCommand's tenant-registry/connection-switching
 * machinery.
 */
uses(KernelTestCase::class);

function makeFixedAsset(
    EntityManagerInterface $em,
    string $cost,
    int $usefulLifeMonths,
    \DateTimeImmutable $purchaseDate,
): FixedAsset {
    $asset = new FixedAsset()
        ->setDescription('Impresora de prueba')
        ->setPurchaseDate($purchaseDate)
        ->setCost($cost)
        ->setUsefulLifeMonths($usefulLifeMonths);
    $em->persist($asset);

    return $asset;
}

function newDepreciationCommand(
    EntityManagerInterface $em,
    \Psr\Log\LoggerInterface $logger,
): GenerateDepreciationEntriesCommand {
    $command = new GenerateDepreciationEntriesCommand($em, new FixedAssetService());

    // logger is normally injected by the container via #[Required] initDependencies();
    // set directly since we bypass PerTenantCommand::execute() (and its tenant-registry
    // wiring) to call executeTenantCommand() straight, exercising only this command's logic.
    $loggerProperty = new \ReflectionProperty(\Nubit\Platform\Symfony\Console\PerTenantCommand::class, 'logger');
    $loggerProperty->setValue($command, $logger);

    return $command;
}

function runDepreciationCommand(GenerateDepreciationEntriesCommand $command): int
{
    $ref = new \ReflectionMethod($command, 'executeTenantCommand');

    return $ref->invoke($command, new ArrayInput([]), new BufferedOutput());
}

beforeEach(function (): void {
    self::bootKernel();
    $this->em = static::getContainer()->get(EntityManagerInterface::class);
    $this->connection = static::getContainer()->get('doctrine.dbal.tenant_connection');
});

it('generates the first depreciation entry for an active asset', function (): void {
    $today = new \DateTimeImmutable('today', new \DateTimeZone('America/Lima'));
    $asset = makeFixedAsset($this->em, '1200.00', 12, $today->modify('-1 month'));
    $this->em->flush();

    $command = newDepreciationCommand($this->em, static::getContainer()->get('logger'));
    $exitCode = runDepreciationCommand($command);

    expect($exitCode)->toBe(0);

    $entries = $this->connection->fetchAllAssociative('SELECT period, amount, accumulated_after FROM asset_depreciation_entry WHERE fixed_asset_id = ? ORDER BY period ASC', [$asset->getId()]);
    $updated = $this->connection->fetchAssociative('SELECT accumulated_depreciation, status FROM fixed_asset WHERE id = ?', [$asset->getId()]);

    expect($entries)
        ->toHaveCount(2)
        ->and($entries[0]['amount'])
        ->toBe('100.00')
        ->and($entries[1]['amount'])
        ->toBe('100.00')
        ->and($updated['accumulated_depreciation'])
        ->toBe('200.00')
        ->and($updated['status'])
        ->toBe(FixedAsset::ACTIVE);
});

it('does not generate entries for a disposed asset', function (): void {
    $today = new \DateTimeImmutable('today', new \DateTimeZone('America/Lima'));
    $asset = makeFixedAsset($this->em, '1200.00', 12, $today->modify('-1 month'));
    $asset->setStatus(FixedAsset::DISPOSED);
    $this->em->flush();

    $command = newDepreciationCommand($this->em, static::getContainer()->get('logger'));
    $exitCode = runDepreciationCommand($command);

    expect($exitCode)->toBe(0);

    $entryCount =
        (int) $this->connection->fetchOne('SELECT COUNT(*) FROM asset_depreciation_entry WHERE fixed_asset_id = ?', [$asset->getId()]);
    expect($entryCount)->toBe(0);
});

it('is idempotent across repeated runs in the same month', function (): void {
    $today = new \DateTimeImmutable('today', new \DateTimeZone('America/Lima'));
    $asset = makeFixedAsset($this->em, '1200.00', 12, $today);
    $this->em->flush();

    $command = newDepreciationCommand($this->em, static::getContainer()->get('logger'));
    runDepreciationCommand($command);
    runDepreciationCommand($command);

    $entryCount =
        (int) $this->connection->fetchOne('SELECT COUNT(*) FROM asset_depreciation_entry WHERE fixed_asset_id = ?', [$asset->getId()]);
    expect($entryCount)->toBe(1);
});

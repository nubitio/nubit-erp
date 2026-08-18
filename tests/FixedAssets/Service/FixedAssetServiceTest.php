<?php

declare(strict_types=1);

namespace App\Tests\FixedAssets\Service;

use App\FixedAssets\Entity\FixedAsset;
use App\FixedAssets\Service\FixedAssetService;
use DateTimeImmutable;
use Nubit\Platform\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

final class FixedAssetServiceTest extends TestCase
{
    public function testTransitionToAllowsActiveToFullyDepreciated(): void
    {
        $asset = new FixedAsset();
        static::assertSame(FixedAsset::ACTIVE, $asset->getStatus());

        new FixedAssetService()->transitionTo($asset, FixedAsset::FULLY_DEPRECIATED);

        static::assertSame(FixedAsset::FULLY_DEPRECIATED, $asset->getStatus());
    }

    public function testTransitionToAllowsActiveToDisposed(): void
    {
        $asset = new FixedAsset();

        new FixedAssetService()->transitionTo($asset, FixedAsset::DISPOSED);

        static::assertSame(FixedAsset::DISPOSED, $asset->getStatus());
    }

    public function testTransitionToAllowsFullyDepreciatedToDisposed(): void
    {
        $asset = new FixedAsset()->setStatus(FixedAsset::FULLY_DEPRECIATED);

        new FixedAssetService()->transitionTo($asset, FixedAsset::DISPOSED);

        static::assertSame(FixedAsset::DISPOSED, $asset->getStatus());
    }

    public function testTransitionToRejectsTransitionFromTerminalDisposed(): void
    {
        $asset = new FixedAsset()->setStatus(FixedAsset::DISPOSED);

        $this->expectException(ValidationException::class);
        new FixedAssetService()->transitionTo($asset, FixedAsset::ACTIVE);
    }

    public function testTransitionToRejectsFullyDepreciatedBackToActive(): void
    {
        $asset = new FixedAsset()->setStatus(FixedAsset::FULLY_DEPRECIATED);

        $this->expectException(ValidationException::class);
        new FixedAssetService()->transitionTo($asset, FixedAsset::ACTIVE);
    }

    public function testGenerateEntriesThroughGeneratesFirstMonthOnly(): void
    {
        $asset = $this->makeAsset(cost: '1200.00', usefulLifeMonths: 12, purchaseDate: '2026-01-15');

        $entries = new FixedAssetService()->generateEntriesThrough($asset, null, new DateTimeImmutable('2026-01-20'));

        static::assertCount(1, $entries);
        static::assertSame('2026-01-01', $entries[0]->getPeriod()->format('Y-m-d'));
        static::assertSame('100.00', $entries[0]->getAmount());
        static::assertSame('100.00', $entries[0]->getAccumulatedAfter());
        static::assertSame('100.00', $asset->getAccumulatedDepreciation());
        static::assertSame(FixedAsset::ACTIVE, $asset->getStatus());
    }

    public function testGenerateEntriesThroughContinuesFromLastGeneratedPeriod(): void
    {
        $asset = $this->makeAsset(cost: '1200.00', usefulLifeMonths: 12, purchaseDate: '2026-01-15');
        $asset->setAccumulatedDepreciation('100.00');

        $entries = new FixedAssetService()->generateEntriesThrough(
            $asset,
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-12-31'),
        );

        static::assertCount(11, $entries);
        static::assertSame('2026-02-01', $entries[0]->getPeriod()->format('Y-m-d'));
        static::assertSame('2026-12-01', $entries[10]->getPeriod()->format('Y-m-d'));
        static::assertSame('1200.00', $asset->getAccumulatedDepreciation());
        static::assertSame(FixedAsset::FULLY_DEPRECIATED, $asset->getStatus());
    }

    public function testGenerateEntriesThroughPlugsTheFinalEntryToLandExactlyOnCost(): void
    {
        // 10.01 / 3 = 3.336... rounds UP to 3.34 (HALF_UP, scale 2); two
        // entries of 3.34 leave only 3.33 remaining, so the third entry
        // must be trimmed down to that remainder to avoid overshooting cost.
        $asset = $this->makeAsset(cost: '10.01', usefulLifeMonths: 3, purchaseDate: '2026-01-01');

        $entries = new FixedAssetService()->generateEntriesThrough($asset, null, new DateTimeImmutable('2026-03-31'));

        static::assertCount(3, $entries);
        static::assertSame('3.34', $entries[0]->getAmount());
        static::assertSame('3.34', $entries[1]->getAmount());
        static::assertSame('3.33', $entries[2]->getAmount());
        static::assertSame('10.01', $asset->getAccumulatedDepreciation());
        static::assertSame(FixedAsset::FULLY_DEPRECIATED, $asset->getStatus());
    }

    public function testGenerateEntriesThroughReturnsNothingWhenNotActive(): void
    {
        $asset = $this->makeAsset(cost: '1200.00', usefulLifeMonths: 12, purchaseDate: '2026-01-01');
        $asset->setStatus(FixedAsset::DISPOSED);

        $entries = new FixedAssetService()->generateEntriesThrough($asset, null, new DateTimeImmutable('2026-06-01'));

        static::assertSame([], $entries);
    }

    public function testGenerateEntriesThroughReturnsNothingWhenAlreadyFullyDepreciated(): void
    {
        $asset = $this->makeAsset(cost: '1200.00', usefulLifeMonths: 12, purchaseDate: '2026-01-01');
        $asset->setAccumulatedDepreciation('1200.00');

        $entries = new FixedAssetService()->generateEntriesThrough(
            $asset,
            new DateTimeImmutable('2026-12-01'),
            new DateTimeImmutable('2027-06-01'),
        );

        static::assertSame([], $entries);
    }

    private function makeAsset(string $cost, int $usefulLifeMonths, string $purchaseDate): FixedAsset
    {
        return new FixedAsset()
            ->setDescription('Laptop de prueba')
            ->setPurchaseDate(new DateTimeImmutable($purchaseDate))
            ->setCost($cost)
            ->setUsefulLifeMonths($usefulLifeMonths);
    }
}

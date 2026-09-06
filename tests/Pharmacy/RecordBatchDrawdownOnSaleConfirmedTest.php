<?php

declare(strict_types=1);

namespace App\Tests\Pharmacy;

use App\Pharmacy\Entity\PharmacyBatch;
use App\Pharmacy\Entity\PharmacyBatchMovement;
use App\Pharmacy\Entity\PharmacyProduct;
use App\Pharmacy\Enum\ExpiryControl;
use App\Pharmacy\MessageHandler\RecordBatchDrawdownOnSaleConfirmed;
use App\Pharmacy\Repository\PharmacyBatchMovementRepository;
use App\Pharmacy\Repository\PharmacyBatchRepository;
use App\Pharmacy\Repository\PharmacyProductRepository;
use App\Pharmacy\Service\PharmacyCatalogService;
use App\Sales\Event\SaleConfirmed;
use App\Sales\Event\SaleConfirmedLine;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class RecordBatchDrawdownOnSaleConfirmedTest extends TestCase
{
    public function testRecordsAFefoMovementForAPharmacyLine(): void
    {
        $persisted = $this->handle(
            [new SaleConfirmedLine('7', '2')],
            profile: new PharmacyProduct(7)->setExpiryControl(ExpiryControl::Block),
            batches: [$this->batch(7, 'OLD', '2028-01-01', 91), $this->batch(7, 'NEW', '2029-01-01', 92)],
        );

        self::assertCount(1, $persisted);
        $movement = $persisted[0];
        self::assertInstanceOf(PharmacyBatchMovement::class, $movement);
        self::assertSame(91, $movement->getBatchId());
        self::assertSame(7, $movement->getProductId());
        self::assertSame('sale-1', $movement->getSaleId());
        self::assertSame('2', $movement->getQuantity());
    }

    public function testSkipsNonPharmacyAndNoneControlLines(): void
    {
        $noProfile = $this->handle([new SaleConfirmedLine('1', '1')], profile: null, batches: []);
        self::assertSame([], $noProfile);

        $noneControl = $this->handle(
            [new SaleConfirmedLine('1', '1')],
            profile: new PharmacyProduct(1)->setExpiryControl(ExpiryControl::None),
            batches: [$this->batch(1, 'B', '2029-01-01', 5)],
        );
        self::assertSame([], $noneControl);
    }

    public function testIsIdempotentPerSale(): void
    {
        $persisted = $this->handle(
            [new SaleConfirmedLine('7', '1')],
            profile: new PharmacyProduct(7)->setExpiryControl(ExpiryControl::Block),
            batches: [$this->batch(7, 'B', '2029-01-01', 5)],
            alreadyRecorded: true,
        );

        self::assertSame([], $persisted);
    }

    public function testFallsBackToEarliestBatchWhenAllAreExpired(): void
    {
        $persisted = $this->handle(
            [new SaleConfirmedLine('7', '1')],
            profile: new PharmacyProduct(7)->setExpiryControl(ExpiryControl::Warn),
            batches: [$this->batch(7, 'OLD', '2000-01-01', 60), $this->batch(7, 'LESS-OLD', '2001-01-01', 61)],
        );

        self::assertCount(1, $persisted);
        self::assertSame(60, $persisted[0]->getBatchId());
    }

    /**
     * @param list<SaleConfirmedLine> $lines
     * @param list<PharmacyBatch> $batches
     * @return list<PharmacyBatchMovement>
     */
    private function handle(
        array $lines,
        ?PharmacyProduct $profile,
        array $batches,
        bool $alreadyRecorded = false,
    ): array {
        $productRepo = $this->createMock(PharmacyProductRepository::class);
        $productRepo->method('findForProduct')->willReturn($profile);

        $batchRepo = $this->createMock(PharmacyBatchRepository::class);
        $batchRepo->method('fefoForProduct')->willReturn($batches);

        $movementRepo = $this->createMock(PharmacyBatchMovementRepository::class);
        $movementRepo->method('existsForSale')->willReturn($alreadyRecorded);

        $captured = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$captured): void {
            if ($entity instanceof PharmacyBatchMovement) {
                $captured[] = $entity;
            }
        });

        (new RecordBatchDrawdownOnSaleConfirmed(
            new PharmacyCatalogService($productRepo, $batchRepo),
            $movementRepo,
            $em,
        ))(new SaleConfirmed('sale-1', 'w1', $lines));

        return $captured;
    }

    private function batch(int $productId, string $number, string $expiry, int $id): PharmacyBatch
    {
        $batch = new PharmacyBatch($productId, $number, new DateTimeImmutable($expiry));
        $reflection = new \ReflectionProperty(PharmacyBatch::class, 'id');
        $reflection->setValue($batch, $id);

        return $batch;
    }
}

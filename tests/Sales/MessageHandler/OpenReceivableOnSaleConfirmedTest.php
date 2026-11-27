<?php

declare(strict_types=1);

namespace App\Tests\Sales\MessageHandler;

use App\Sales\Entity\SaleRecord;
use App\Sales\Event\SaleConfirmed;
use App\Sales\MessageHandler\OpenReceivableOnSaleConfirmed;
use App\Sales\Receivable\ReceivableOpener;
use App\Sales\Repository\SaleRecordRepository;
use App\Settings\Capability\CapabilityKey;
use App\Settings\Entity\TenantCapability;
use App\Settings\Repository\TenantCapabilityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class OpenReceivableOnSaleConfirmedTest extends TestCase
{
    public function testNothingHappensWhileTheCapabilityIsOff(): void
    {
        $opener = $this->createMock(ReceivableOpener::class);
        $opener->expects(self::never())->method('open');

        $this->handler(false, $this->createStub(SaleRecordRepository::class), $opener)(
            new SaleConfirmed('s1', 'w1', []),
        );
    }

    public function testAnOpenerFailureNeverReachesTheSale(): void
    {
        $sale = new SaleRecord()->setReceivableAccount(true);
        $sales = $this->createStub(SaleRecordRepository::class);
        $sales->method('findByAnyId')->willReturn($sale);
        $opener = $this->createStub(ReceivableOpener::class);
        $opener->method('open')->willThrowException(new \RuntimeException('db down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('receivable.open_failed', self::anything());

        (new OpenReceivableOnSaleConfirmed($sales, $opener, $this->capabilities(true), $logger))(
            new SaleConfirmed('s1', 'w1', []),
        );
    }

    public function testACreditSaleIsOpenedWhenTheCapabilityIsOn(): void
    {
        $sale = new SaleRecord()->setReceivableAccount(true);
        $sales = $this->createStub(SaleRecordRepository::class);
        $sales->method('findByAnyId')->willReturn($sale);
        $opener = $this->createMock(ReceivableOpener::class);
        $opener->expects(self::once())->method('open')->with($sale);

        $this->handler(true, $sales, $opener)(new SaleConfirmed('s1', 'w1', []));
    }

    private function handler(
        bool $enabled,
        SaleRecordRepository $sales,
        ReceivableOpener $opener,
    ): OpenReceivableOnSaleConfirmed {
        return new OpenReceivableOnSaleConfirmed(
            $sales,
            $opener,
            $this->capabilities($enabled),
            $this->createStub(LoggerInterface::class),
        );
    }

    private function capabilities(bool $enabled): TenantCapabilityRepository
    {
        $repository = $this->createStub(TenantCapabilityRepository::class);
        $repository
            ->method('findByKey')
            ->willReturn(
                $enabled ? new TenantCapability(CapabilityKey::FinanceReceivablesV2->value)->setEnabled(true) : null,
            );

        return $repository;
    }
}

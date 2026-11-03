<?php

declare(strict_types=1);

namespace App\Tests\Billing\Adapter;

use App\Billing\Adapter\SalePublicReceiptLink;
use App\Billing\Receipt\ReceiptPayload;
use App\Sales\Entity\SaleRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SalePublicReceiptLinkTest extends TestCase
{
    public function testBuildsTheAbsolutePublicReceiptUrlForAnInvoicedSale(): void
    {
        $sale = $this->createMock(SaleRecord::class);
        $sale->method('getTaxDocumentId')->willReturn('tax-doc-1');
        $sale->method('getDocumentType')->willReturn('01');
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls
            ->expects(self::once())
            ->method('generate')
            ->with(
                'receipt_show',
                ['payload' => ReceiptPayload::encode('01', 'tax-doc-1')],
                UrlGeneratorInterface::ABSOLUTE_URL,
            )
            ->willReturn('https://erp.test/receipt/abc');

        self::assertSame('https://erp.test/receipt/abc', new SalePublicReceiptLink($urls)->publicUrl($sale));
    }

    public function testReturnsNullWhenTheSaleHasNoVoucher(): void
    {
        $sale = $this->createMock(SaleRecord::class);
        $sale->method('getTaxDocumentId')->willReturn(null);
        $sale->method('getDocumentType')->willReturn(null);
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects(self::never())->method('generate');

        self::assertNull(new SalePublicReceiptLink($urls)->publicUrl($sale));
    }
}

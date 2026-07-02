<?php

declare(strict_types=1);

namespace App\Tests\Billing\Shared;

use App\Billing\Invoice\Domain\InvoiceSendProcessResult;
use App\Billing\Invoice\Domain\Voucher;
use App\Billing\Invoice\Domain\VoucherItem;
use App\Billing\Message\SendInvoiceMessage;
use App\Billing\Receipt\Domain\Receipt;
use App\Billing\Receipt\Domain\ReceiptType;
use App\Billing\Sale\Void\SunatVoidResponse;
use App\Billing\Shared\Enum\ChargeType;
use App\Billing\Shared\Enum\OperationType;
use App\Billing\Shared\ErrorMessages;
use App\Billing\Sunat\Model\Response\SummaryResponse;
use PHPUnit\Framework\TestCase;

/**
 * Domain value-objects, enums, and messages — pure unit tests, no I/O.
 */
final class BillingDomainValueObjectsTest extends TestCase
{
    // ── SendInvoiceMessage ────────────────────────────────────────────────────

    public function testSendInvoiceMessageExposesProperties(): void
    {
        $msg = new SendInvoiceMessage('doc-123', 'tenant-abc');

        self::assertSame('doc-123', $msg->taxDocumentId);
        self::assertSame('tenant-abc', $msg->getTenantId());
    }

    // ── Receipt ───────────────────────────────────────────────────────────────

    public function testReceiptHoldsAllFields(): void
    {
        $r = new Receipt('Empresa SA', 'FACTURA', 'F001', '00042', '2026-04-30', 250.00);

        self::assertSame('Empresa SA', $r->company);
        self::assertSame('FACTURA', $r->type);
        self::assertSame('F001', $r->series);
        self::assertSame('00042', $r->number);
        self::assertSame('2026-04-30', $r->date);
        self::assertSame(250.00, $r->total);
        self::assertSame('PEN', $r->currency); // default
    }

    public function testReceiptAcceptsCustomCurrency(): void
    {
        $r = new Receipt('Empresa SA', 'BOLETA', 'B001', '00001', '2026-04-30', 50.00, 'USD');

        self::assertSame('USD', $r->currency);
    }

    // ── ReceiptType ───────────────────────────────────────────────────────────

    public function testReceiptTypeValues(): void
    {
        self::assertSame('01', ReceiptType::FACTURA->value);
        self::assertSame('03', ReceiptType::BOLETA->value);
        self::assertSame('07', ReceiptType::NOTA_CREDITO->value);
        self::assertSame('08', ReceiptType::NOTA_DEBITO->value);
    }

    public function testReceiptTypeFromValue(): void
    {
        self::assertSame(ReceiptType::FACTURA, ReceiptType::from('01'));
        self::assertSame(ReceiptType::NOTA_CREDITO, ReceiptType::from('07'));
    }

    // ── InvoiceSendProcessResult ──────────────────────────────────────────────

    public function testInvoiceSendProcessResultSentWithNoError(): void
    {
        $r = new InvoiceSendProcessResult(sent: true, error: null, paused: false, skipReason: null);

        self::assertTrue($r->sent);
        self::assertNull($r->error);
        self::assertFalse($r->paused);
        self::assertNull($r->skipReason);
    }

    public function testInvoiceSendProcessResultPaused(): void
    {
        $r = new InvoiceSendProcessResult(sent: false, error: null, paused: true, skipReason: 'circuit_open');

        self::assertFalse($r->sent);
        self::assertTrue($r->paused);
        self::assertSame('circuit_open', $r->skipReason);
    }

    public function testInvoiceSendProcessResultError(): void
    {
        $r = new InvoiceSendProcessResult(sent: false, error: 'SUNAT timeout', paused: false, skipReason: null);

        self::assertFalse($r->sent);
        self::assertSame('SUNAT timeout', $r->error);
    }

    // ── Voucher ───────────────────────────────────────────────────────────────

    public function testVoucherDefaultsAreNull(): void
    {
        $v = new Voucher();

        self::assertNull($v->sale_id);
        self::assertNull($v->voucher_type);
        self::assertSame([], $v->voucher_items);
    }

    public function testVoucherTotalTextFormatsCorrectly(): void
    {
        $v = new Voucher(voucher_sale_amount: '100.50');
        $text = $v->getVoucherTotalText();

        // Should start with "Son:" and contain the number in words (Spanish)
        self::assertStringStartsWith('Son:', $text);
        self::assertStringContainsStringIgnoringCase('100', $text);
    }

    // ── VoucherItem ───────────────────────────────────────────────────────────

    public function testVoucherItemDefaults(): void
    {
        $item = new VoucherItem();

        self::assertNull($item->quantity);
        self::assertNull($item->product_name);
        self::assertNull($item->unit_price);
    }

    // ── SunatVoidResponse ─────────────────────────────────────────────────────

    public function testSunatVoidResponseGetCorrelation(): void
    {
        $summary = new SummaryResponse();
        $r = new SunatVoidResponse(
            rawResponse: $summary,
            name: '20123456789-RC-20241201-00001',
            ticket: 'TKT123',
            success: true,
            message: null,
        );

        self::assertSame('00001', $r->getCorrelation());
        self::assertTrue($r->success);
        self::assertSame('TKT123', $r->ticket);
    }

    public function testSunatVoidResponseCorrelationNullWhenNameHasFewerParts(): void
    {
        $summary = new SummaryResponse();
        $r = new SunatVoidResponse(
            rawResponse: $summary,
            name: 'short',
            ticket: 'TKT000',
            success: false,
            message: 'Error',
        );

        self::assertNull($r->getCorrelation());
    }

    // ── Enums ─────────────────────────────────────────────────────────────────

    public function testChargeTypeValues(): void
    {
        self::assertSame('46', ChargeType::CONSUMPTION_SURCHARGE->value);
        self::assertSame('02', ChargeType::DISCOUNT->value);
    }

    public function testOperationTypeValues(): void
    {
        self::assertSame('0101', OperationType::SALE->value);
        self::assertSame('1001', OperationType::DETRACTION_SALE->value);
    }

    // ── ErrorMessages ─────────────────────────────────────────────────────────

    public function testErrorMessagesAreNonEmpty(): void
    {
        self::assertNotEmpty(ErrorMessages::SALE_ALREADY_HAS_VOUCHER);
        self::assertNotEmpty(ErrorMessages::NO_SERIES_CONFIGURED);
        self::assertNotEmpty(ErrorMessages::INVOICE_NOT_FOUND);
        self::assertNotEmpty(ErrorMessages::VOID_SUCCESSFUL);
    }
}

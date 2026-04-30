<?php

declare(strict_types=1);

namespace App\Tests\Billing;

use App\Billing\Entity\TaxDocument;
use App\Billing\Entity\TaxDocumentEvent;
use App\Billing\Receipt\ReceiptPayload;
use PHPUnit\Framework\TestCase;

final class BillingDomainTest extends TestCase
{
    // ── ReceiptPayload ────────────────────────────────────────────────────────

    public function testEncodeProducesBase64(): void
    {
        $encoded = ReceiptPayload::encode('boleta', 'abc-123');
        $decoded = json_decode(base64_decode($encoded), true);

        self::assertSame('boleta', $decoded['type']);
        self::assertSame('abc-123', $decoded['id']);
    }

    public function testPublicUrlStartsWithSlashReceipt(): void
    {
        $url = ReceiptPayload::publicUrl('factura', 'xyz-456');

        self::assertStringStartsWith('/receipt/', $url);
    }

    public function testPublicUrlEncodesTypeAndId(): void
    {
        $url     = ReceiptPayload::publicUrl('boleta', 'sale-1');
        $encoded = str_replace('/receipt/', '', $url);
        $decoded = json_decode(base64_decode($encoded), true);

        self::assertSame('boleta', $decoded['type']);
        self::assertSame('sale-1', $decoded['id']);
    }

    public function testEncodeIsDeterministic(): void
    {
        $a = ReceiptPayload::encode('type', 'id-1');
        $b = ReceiptPayload::encode('type', 'id-1');

        self::assertSame($a, $b);
    }

    public function testDifferentInputsProduceDifferentEncoding(): void
    {
        $a = ReceiptPayload::encode('boleta', 'id-1');
        $b = ReceiptPayload::encode('factura', 'id-1');

        self::assertNotSame($a, $b);
    }

    // ── TaxDocumentEvent entity ───────────────────────────────────────────────

    public function testTaxDocumentEventDefaults(): void
    {
        $event = new TaxDocumentEvent();

        self::assertNull($event->getId());
        self::assertNull($event->getStatus());
        self::assertNull($event->getCode());
        self::assertNull($event->getMessage());
    }

    public function testTaxDocumentEventEventTypeRoundTrip(): void
    {
        $event = new TaxDocumentEvent();
        $doc   = new TaxDocument();

        $event->setEventType('SENT');
        $event->setTaxDocument($doc);

        self::assertSame('SENT', $event->getEventType());
        self::assertSame($doc, $event->getTaxDocument());
    }

    public function testTaxDocumentEventStatusRoundTrip(): void
    {
        $event = (new TaxDocumentEvent())->setStatus('ACCEPTED');

        self::assertSame('ACCEPTED', $event->getStatus());
    }

    public function testTaxDocumentEventMessageRoundTrip(): void
    {
        $event = (new TaxDocumentEvent())->setMessage('CDR received');

        self::assertSame('CDR received', $event->getMessage());
    }
}

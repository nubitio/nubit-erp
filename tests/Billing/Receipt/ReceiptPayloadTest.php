<?php

declare(strict_types=1);

namespace App\Tests\Billing\Receipt;

use App\Billing\Receipt\ReceiptPayload;
use PHPUnit\Framework\TestCase;

final class ReceiptPayloadTest extends TestCase
{
    public function testEncodeProducesBase64Json(): void
    {
        $encoded = ReceiptPayload::encode('01', 'doc-uuid-123');

        $decoded = json_decode(base64_decode($encoded), true);
        self::assertSame('01', $decoded['type']);
        self::assertSame('doc-uuid-123', $decoded['id']);
    }

    public function testPublicUrlStartsWithReceiptPath(): void
    {
        $url = ReceiptPayload::publicUrl('03', 'doc-456');

        self::assertStringStartsWith('/receipt/', $url);
    }

    public function testPublicUrlContainsEncodedPayload(): void
    {
        $url = ReceiptPayload::publicUrl('01', 'abc');
        $payload = substr($url, strlen('/receipt/'));

        $decoded = json_decode(base64_decode($payload), true);
        self::assertSame('01', $decoded['type']);
        self::assertSame('abc', $decoded['id']);
    }

    public function testEncodeDifferentTypesProduceDifferentResults(): void
    {
        self::assertNotSame(
            ReceiptPayload::encode('01', 'same-id'),
            ReceiptPayload::encode('03', 'same-id'),
        );
    }
}

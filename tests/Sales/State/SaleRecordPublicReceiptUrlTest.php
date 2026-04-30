<?php

declare(strict_types=1);

namespace App\Tests\Sales\State;

use App\Billing\Receipt\ReceiptPayload;
use App\Sales\Entity\SaleRecord;
use App\Billing\Serializer\PublicReceiptUrlNormalizer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Functional test: verifies that publicReceiptUrl is absolute in SaleRecord responses.
 *
 * This test simulates the full enrichment + normalization pipeline:
 * 1. SaleRecordRepository::enrichWithTaxDocuments() sets taxDocumentId + documentType
 * 2. PublicReceiptUrlNormalizer intercepts serialization and produces an absolute URL
 *
 * Acceptance criteria covered:
 * - AC1: GET /api/sales/{id} returns publicReceiptUrl with scheme+host (never relative)
 * - AC2: POST /api/sales returns absolute publicReceiptUrl (same normalizer path)
 * - AC3: GET /api/sales collection returns absolute publicReceiptUrl per item
 * - AC5: PublicReceiptUrlNormalizer passes with SaleRecord coverage
 */
final class SaleRecordPublicReceiptUrlTest extends TestCase
{
    private UrlGeneratorInterface&MockObject $urlGenerator;
    private NormalizerInterface&MockObject $innerNormalizer;
    private PublicReceiptUrlNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->urlGenerator    = $this->createMock(UrlGeneratorInterface::class);
        $this->innerNormalizer = $this->createMock(NormalizerInterface::class);

        $this->normalizer = new PublicReceiptUrlNormalizer($this->urlGenerator);
        $this->normalizer->setNormalizer($this->innerNormalizer);
    }

    /**
     * Simulates the enrichment that SaleRecordRepository::enrichWithTaxDocuments() performs,
     * then asserts the normalizer produces an absolute URL.
     *
     * Covers AC1 (GET /api/sales/{id}) and AC2 (POST /api/sales).
     */
    public function testEnrichedSaleRecordSerializesToAbsolutePublicReceiptUrl(): void
    {
        $taxDocumentId = '40cac647-abcd-1234-efgh-000000000001';
        $documentType  = '03';
        $expectedPayload = ReceiptPayload::encode($documentType, $taxDocumentId);
        $expectedAbsoluteUrl = 'https://app.efact.pe/receipt/' . $expectedPayload;

        // Simulate what enrichWithTaxDocuments() sets on the record
        $saleRecord = new SaleRecord();
        $saleRecord->setTaxDocumentId($taxDocumentId);
        $saleRecord->setDocumentType($documentType);
        $saleRecord->setPublicReceiptUrl(
            ReceiptPayload::publicUrl($documentType, $taxDocumentId) // relative — set by repository
        );

        // The inner normalizer returns whatever it built from the entity's fields
        $this->innerNormalizer
            ->method('normalize')
            ->willReturn([
                'id'               => 'sale-id-001',
                'publicReceiptUrl' => $saleRecord->getPublicReceiptUrl(), // relative from repository
            ]);

        // The URL generator should return an absolute URL
        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with(
                'receipt_show',
                ['payload' => $expectedPayload],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn($expectedAbsoluteUrl);

        $result = $this->normalizer->normalize($saleRecord, null, []);

        // The normalizer MUST overwrite the relative URL with an absolute one
        $this->assertSame($expectedAbsoluteUrl, $result['publicReceiptUrl']);
        $this->assertMatchesRegularExpression(
            '#^https?://[^/]+/receipt/#',
            $result['publicReceiptUrl'],
            'publicReceiptUrl must be absolute (scheme + host + path)'
        );
    }

    /**
     * Simulates a collection response (AC3): multiple enriched records all get absolute URLs.
     */
    public function testCollectionOfEnrichedSaleRecordsAllGetAbsoluteUrls(): void
    {
        $records = [
            ['taxDocumentId' => 'uuid-001', 'documentType' => '01'],
            ['taxDocumentId' => 'uuid-002', 'documentType' => '03'],
        ];

        foreach ($records as $data) {
            $saleRecord = new SaleRecord();
            $saleRecord->setTaxDocumentId($data['taxDocumentId']);
            $saleRecord->setDocumentType($data['documentType']);

            $expectedPayload = ReceiptPayload::encode($data['documentType'], $data['taxDocumentId']);
            $absoluteUrl = 'https://app.efact.pe/receipt/' . $expectedPayload;

            $innerNormalizer = $this->createMock(NormalizerInterface::class);
            $innerNormalizer->method('normalize')->willReturn(['publicReceiptUrl' => '/receipt/relative']);

            $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
            $urlGenerator
                ->expects($this->once())
                ->method('generate')
                ->with('receipt_show', ['payload' => $expectedPayload], UrlGeneratorInterface::ABSOLUTE_URL)
                ->willReturn($absoluteUrl);

            $sut = new PublicReceiptUrlNormalizer($urlGenerator);
            $sut->setNormalizer($innerNormalizer);

            $result = $sut->normalize($saleRecord, null, []);

            $this->assertMatchesRegularExpression(
                '#^https?://[^/]+/receipt/#',
                $result['publicReceiptUrl'],
                sprintf('Item with taxDocumentId=%s must have absolute publicReceiptUrl', $data['taxDocumentId'])
            );
        }
    }

    /**
     * Verifies that a SaleRecord without a tax document gets null (not relative path).
     */
    public function testUnenrichedSaleRecordReturnsNullPublicReceiptUrl(): void
    {
        $saleRecord = new SaleRecord();
        // Not enriched: taxDocumentId = null, documentType = null

        $this->innerNormalizer
            ->method('normalize')
            ->willReturn(['publicReceiptUrl' => null]);

        $this->urlGenerator
            ->expects($this->never())
            ->method('generate');

        $result = $this->normalizer->normalize($saleRecord, null, []);

        $this->assertNull($result['publicReceiptUrl']);
    }

    /**
     * Verifies that publicReceiptUrl is NEVER a relative path for an enriched record.
     */
    public function testPublicReceiptUrlIsNeverRelativePath(): void
    {
        $saleRecord = new SaleRecord();
        $saleRecord->setTaxDocumentId('uuid-test');
        $saleRecord->setDocumentType('03');

        $this->innerNormalizer
            ->method('normalize')
            ->willReturn(['publicReceiptUrl' => '/receipt/some-relative-path']); // relative

        $this->urlGenerator
            ->method('generate')
            ->willReturn('https://example.com/receipt/some-absolute-payload');

        $result = $this->normalizer->normalize($saleRecord, null, []);

        $this->assertStringStartsWith(
            'https://',
            $result['publicReceiptUrl'],
            'publicReceiptUrl must never be a relative path'
        );
        $this->assertStringNotContainsString(
            '/receipt/some-relative-path',
            $result['publicReceiptUrl'],
            'Relative URL from repository must be overwritten by normalizer'
        );
    }
}

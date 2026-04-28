<?php

declare(strict_types=1);

namespace App\Tests\Billing\Serializer;

use App\Core\Util\ReceiptPayload;
use App\Sales\Entity\SaleRecord;
use App\Billing\Serializer\PublicReceiptUrlNormalizer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class PublicReceiptUrlNormalizerTest extends TestCase
{
    private const string ALREADY_CALLED = 'public_receipt_url_normalizer_called';

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

    public function testSaleRecordWithTaxDocumentReturnsAbsoluteUrl(): void
    {
        $taxDocumentId   = 'some-uuid-1234';
        $documentType    = '03';
        $expectedPayload = ReceiptPayload::encode($documentType, $taxDocumentId);
        $absoluteUrl     = 'https://app.efact.pe/receipt/' . $expectedPayload;

        $saleRecord = new SaleRecord();
        $saleRecord->setTaxDocumentId($taxDocumentId);
        $saleRecord->setDocumentType($documentType);

        $this->innerNormalizer
            ->expects($this->once())
            ->method('normalize')
            ->willReturn(['publicReceiptUrl' => '/receipt/old-relative']);

        $this->urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with(
                'receipt_show',
                ['payload' => $expectedPayload],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn($absoluteUrl);

        $result = $this->normalizer->normalize($saleRecord, null, []);

        $this->assertSame($absoluteUrl, $result['publicReceiptUrl']);
    }

    public function testSaleRecordWithoutTaxDocumentDoesNotGenerateUrl(): void
    {
        $saleRecord = new SaleRecord();
        // taxDocumentId and documentType are both null by default

        $this->innerNormalizer
            ->expects($this->once())
            ->method('normalize')
            ->willReturn(['publicReceiptUrl' => null]);

        $this->urlGenerator
            ->expects($this->never())
            ->method('generate');

        $result = $this->normalizer->normalize($saleRecord, null, []);

        $this->assertNull($result['publicReceiptUrl']);
    }

    public function testSaleRecordWithTaxDocumentIdButNullDocumentTypeDoesNotGenerateUrl(): void
    {
        $saleRecord = new SaleRecord();
        $saleRecord->setTaxDocumentId('some-uuid');
        // documentType is still null

        $this->innerNormalizer
            ->expects($this->once())
            ->method('normalize')
            ->willReturn(['publicReceiptUrl' => '/receipt/relative']);

        $this->urlGenerator
            ->expects($this->never())
            ->method('generate');

        $result = $this->normalizer->normalize($saleRecord, null, []);

        // URL was not overwritten because payload could not be built
        $this->assertSame('/receipt/relative', $result['publicReceiptUrl']);
    }

    public function testSupportsNormalizationReturnsTrueForSaleRecord(): void
    {
        $saleRecord = new SaleRecord();

        $this->assertTrue($this->normalizer->supportsNormalization($saleRecord));
    }

    public function testSupportsNormalizationReturnsFalseWhenAlreadyCalled(): void
    {
        $saleRecord = new SaleRecord();

        $this->assertFalse(
            $this->normalizer->supportsNormalization($saleRecord, null, [self::ALREADY_CALLED => true])
        );
    }

    public function testSupportsNormalizationReturnsFalseForArbitraryObject(): void
    {
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypesIncludesSaleRecord(): void
    {
        $types = $this->normalizer->getSupportedTypes(null);

        $this->assertArrayHasKey(SaleRecord::class, $types);
    }
}

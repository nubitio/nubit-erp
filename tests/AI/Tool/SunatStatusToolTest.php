<?php

declare(strict_types=1);

namespace App\Tests\AI\Tool;

use App\AI\Repository\SunatRejectionQueryInterface;
use App\AI\Tool\SunatStatusTool;
use App\Entity\TaxDocument;
use App\Entity\TaxDocumentEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SunatStatusToolTest extends TestCase
{
    // ------------------------------------------------------------------
    // Helper builders
    // ------------------------------------------------------------------

    private function makeDocument(
        string $series,
        string $number,
        string $documentType,
        string $issueDate = '2026-03-10'
    ): TaxDocument {
        $doc = $this->createStub(TaxDocument::class);
        $doc->method('getSeries')->willReturn($series);
        $doc->method('getNumber')->willReturn($number);
        $doc->method('getDocumentType')->willReturn($documentType);
        $doc->method('getIssueDate')->willReturn(new DateTimeImmutable($issueDate));

        return $doc;
    }

    private function makeEvent(TaxDocument $doc, string $code, ?string $message = null): TaxDocumentEvent
    {
        $event = $this->createStub(TaxDocumentEvent::class);
        $event->method('getTaxDocument')->willReturn($doc);
        $event->method('getCode')->willReturn($code);
        $event->method('getMessage')->willReturn($message);
        $event->method('getCreatedAt')->willReturn(new DateTimeImmutable());

        return $event;
    }

    private function makeTool(SunatRejectionQueryInterface $query): SunatStatusTool
    {
        return new SunatStatusTool($query);
    }

    /**
     * @param list<TaxDocumentEvent> $events
     */
    private function stubQuery(array $events): SunatRejectionQueryInterface
    {
        $stub = $this->createStub(SunatRejectionQueryInterface::class);
        $stub->method('findRejections')->willReturn($events);

        return $stub;
    }

    // ------------------------------------------------------------------
    // Test (a): empty result when no rejections
    // ------------------------------------------------------------------

    public function testReturnsEmptyResultWhenNoRejections(): void
    {
        $tool = $this->makeTool($this->stubQuery([]));

        $result = $tool->sunatStatus();

        self::assertSame([], $result['summary']);
        self::assertSame([], $result['recent_rejections']);
    }

    // ------------------------------------------------------------------
    // Test (b): summary groups by code
    // ------------------------------------------------------------------

    public function testSummaryGroupsByCode(): void
    {
        $doc1 = $this->makeDocument('F001', '00000001', '01');
        $doc2 = $this->makeDocument('F001', '00000002', '01');
        $doc3 = $this->makeDocument('F001', '00000003', '01');
        $doc4 = $this->makeDocument('B001', '00000001', '03');

        $events = [
            $this->makeEvent($doc1, '3000', 'Rechazado por SUNAT'),
            $this->makeEvent($doc2, '3000', 'Rechazado por SUNAT'),
            $this->makeEvent($doc3, '0156', 'Duplicado'),
            $this->makeEvent($doc4, '3000', 'Rechazado por SUNAT'),
        ];

        $tool = $this->makeTool($this->stubQuery($events));
        $result = $tool->sunatStatus();

        // Summary should have 2 entries (2 distinct codes)
        self::assertCount(2, $result['summary']);

        // First entry should be code 3000 with count 3 (most frequent)
        $firstEntry = $result['summary'][0];
        self::assertSame('3000', $firstEntry['code']);
        self::assertSame(3, $firstEntry['count']);
        self::assertSame('El comprobante fue rechazado por SUNAT', $firstEntry['label']);

        // Second entry should be code 0156 with count 1
        $secondEntry = $result['summary'][1];
        self::assertSame('0156', $secondEntry['code']);
        self::assertSame(1, $secondEntry['count']);
    }

    // ------------------------------------------------------------------
    // Test (c): limit is respected in recent_rejections
    // ------------------------------------------------------------------

    public function testLimitIsRespected(): void
    {
        $events = [];
        for ($i = 1; $i <= 15; $i++) {
            $doc = $this->makeDocument('F001', str_pad((string) $i, 8, '0', STR_PAD_LEFT), '01');
            $events[] = $this->makeEvent($doc, '3000', 'Error');
        }

        $tool = $this->makeTool($this->stubQuery($events));
        $result = $tool->sunatStatus(5);

        self::assertCount(5, $result['recent_rejections']);
    }

    public function testLimitIsClampedToFifty(): void
    {
        $events = [];
        for ($i = 1; $i <= 60; $i++) {
            $doc = $this->makeDocument('F001', str_pad((string) $i, 8, '0', STR_PAD_LEFT), '01');
            $events[] = $this->makeEvent($doc, '3000', 'Error');
        }

        $tool = $this->makeTool($this->stubQuery($events));
        $result = $tool->sunatStatus(100); // Request 100, should be clamped to 50

        self::assertCount(50, $result['recent_rejections']);
    }

    // ------------------------------------------------------------------
    // Test (d): unknown codes get a generic label
    // ------------------------------------------------------------------

    public function testUnknownCodeGetsGenericLabel(): void
    {
        $doc = $this->makeDocument('F001', '00000001', '01');
        $events = [$this->makeEvent($doc, '9999', 'Error desconocido')];

        $tool = $this->makeTool($this->stubQuery($events));
        $result = $tool->sunatStatus();

        self::assertCount(1, $result['summary']);
        self::assertSame('9999', $result['summary'][0]['code']);
        self::assertSame('Error SUNAT código 9999', $result['summary'][0]['label']);
    }

    // ------------------------------------------------------------------
    // Additional: recent_rejections structure is correct
    // ------------------------------------------------------------------

    public function testRecentRejectionsContainExpectedFields(): void
    {
        $doc = $this->makeDocument('F001', '00000042', '01', '2026-03-10');
        $events = [$this->makeEvent($doc, '0156', 'Comprobante duplicado')];

        $tool = $this->makeTool($this->stubQuery($events));
        $result = $tool->sunatStatus(10);

        self::assertCount(1, $result['recent_rejections']);
        $rejection = $result['recent_rejections'][0];

        self::assertSame('F001', $rejection['series']);
        self::assertSame('00000042', $rejection['correlative']);
        self::assertSame('01', $rejection['document_type']);
        self::assertSame('0156', $rejection['response_code']);
        self::assertSame('Comprobante duplicado', $rejection['response_description']);
        self::assertStringContainsString('2026-03-10', $rejection['issued_at']);
    }

    // ------------------------------------------------------------------
    // Additional: top-5 summary cap
    // ------------------------------------------------------------------

    public function testSummaryCapsAtFiveEntries(): void
    {
        // Create 7 distinct error codes (1 event each)
        $events = [];
        $codes = ['0100', '0109', '0111', '0112', '0156', '0160', '2075'];
        foreach ($codes as $code) {
            $doc = $this->makeDocument('F001', '0000000' . $code, '01');
            $events[] = $this->makeEvent($doc, $code, 'Error ' . $code);
        }

        $tool = $this->makeTool($this->stubQuery($events));
        $result = $tool->sunatStatus();

        // Summary is limited to top 5
        self::assertCount(5, $result['summary']);
    }

    // ------------------------------------------------------------------
    // Additional: known labels are correctly mapped
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{string, string}>
     */
    public static function knownCodeLabelProvider(): array
    {
        return [
            'code_0109' => ['0109', 'El número de RUC no existe en SUNAT'],
            'code_0111' => ['0111', 'El RUC del emisor no está activo'],
            'code_2800' => ['2800', 'Error en la firma digital del comprobante'],
            'code_3001' => ['3001', 'El comprobante fue anulado'],
        ];
    }

    #[DataProvider('knownCodeLabelProvider')]
    public function testKnownCodesHaveCorrectLabels(string $code, string $expectedLabel): void
    {
        $doc = $this->makeDocument('F001', '00000001', '01');
        $events = [$this->makeEvent($doc, $code, 'test')];

        $tool = $this->makeTool($this->stubQuery($events));
        $result = $tool->sunatStatus();

        self::assertCount(1, $result['summary']);
        self::assertSame($expectedLabel, $result['summary'][0]['label']);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\AI\Tool;

use App\AI\Tool\TaxDocumentTools;
use App\Billing\Entity\TaxDocument;
use App\Billing\Repository\TaxDocumentRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TaxDocumentToolsTest extends TestCase
{
    public function testReturnsNotFoundWhenEmpty(): void
    {
        $repo = $this->createMock(TaxDocumentRepository::class);
        $repo->expects(self::never())->method('find');
        $tools = new TaxDocumentTools($repo);

        self::assertSame(['error' => 'No se encontró el comprobante.'], $tools->taxDocumentStatus());
    }

    public function testLooksUpById(): void
    {
        $document = $this->createStub(TaxDocument::class);
        $document->method('getId')->willReturn('td-1');
        $document->method('getDocumentType')->willReturn('01');
        $document->method('getSeries')->willReturn('F001');
        $document->method('getNumber')->willReturn('1');
        $document->method('getIssueDate')->willReturn(new DateTimeImmutable('2026-08-01'));
        $document->method('getTotal')->willReturn('100.00');
        $document->method('getSunatStatus')->willReturn('ACEPTADO');
        $document->method('getSunatMessage')->willReturn(null);
        $document->method('getCanonicalSaleId')->willReturn('sale-1');

        $repo = $this->createMock(TaxDocumentRepository::class);
        $repo->expects(self::once())->method('find')->with('td-1')->willReturn($document);

        $tools = new TaxDocumentTools($repo);
        $result = $tools->taxDocumentStatus(id: 'td-1');

        self::assertSame('F001', $result['series'] ?? null);
        self::assertSame('ACEPTADO', $result['sunat_status']);
    }
}

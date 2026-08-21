<?php

declare(strict_types=1);

namespace App\Tests\AI\Tool;

use App\AI\Service\ProposedActionFactory;
use App\AI\Tool\ProposeCreditNoteTool;
use App\Billing\Entity\TaxDocument;
use App\Billing\Repository\TaxDocumentRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ProposeCreditNoteToolTest extends TestCase
{
    public function testReturnsErrorWhenDocumentIsMissing(): void
    {
        $repo = $this->createMock(TaxDocumentRepository::class);
        $repo->expects(self::once())->method('find')->with('missing')->willReturn(null);

        $tool = new ProposeCreditNoteTool(
            new ReflectionClass(ProposedActionFactory::class)->newInstanceWithoutConstructor(),
            $repo,
        );

        $result = $tool->proposeCreditNote(reasonCode: '01', taxDocumentId: 'missing');
        self::assertSame(['error' => 'No se encontró el comprobante.'], $result);
    }

    public function testRefusesCreditNoteOnCreditNote(): void
    {
        $document = $this->createStub(TaxDocument::class);
        $document->method('getDocumentType')->willReturn('07');

        $repo = $this->createMock(TaxDocumentRepository::class);
        $repo->expects(self::once())->method('find')->with('td-1')->willReturn($document);

        $tool = new ProposeCreditNoteTool(
            new ReflectionClass(ProposedActionFactory::class)->newInstanceWithoutConstructor(),
            $repo,
        );

        $result = $tool->proposeCreditNote(reasonCode: '01', taxDocumentId: 'td-1');
        self::assertArrayHasKey('error', $result);
        self::assertStringContainsString('otra nota de crédito', $result['error']);
    }

    public function testRefusesRejectedDocumentsAndPointsToReplace(): void
    {
        $document = $this->createStub(TaxDocument::class);
        $document->method('getDocumentType')->willReturn('01');
        $document->method('isVoided')->willReturn(false);
        $document->method('getSeries')->willReturn('F001');
        $document->method('getNumber')->willReturn('12');
        $document->method('getSunatStatus')->willReturn('2108');

        $repo = $this->createMock(TaxDocumentRepository::class);
        $repo->expects(self::once())->method('find')->with('td-1')->willReturn($document);

        $tool = new ProposeCreditNoteTool(
            new ReflectionClass(ProposedActionFactory::class)->newInstanceWithoutConstructor(),
            $repo,
        );

        $result = $tool->proposeCreditNote(reasonCode: '01', taxDocumentId: 'td-1');
        self::assertArrayHasKey('error', $result);
        self::assertStringContainsString('propose_replace_rejected', $result['error']);
    }

    public function testRejectsInvalidReasonCode(): void
    {
        $document = $this->createStub(TaxDocument::class);
        $document->method('getDocumentType')->willReturn('01');
        $document->method('isVoided')->willReturn(false);
        $document->method('getSunatStatus')->willReturn('0');

        $repo = $this->createMock(TaxDocumentRepository::class);
        $repo->expects(self::once())->method('find')->with('td-1')->willReturn($document);

        $tool = new ProposeCreditNoteTool(
            new ReflectionClass(ProposedActionFactory::class)->newInstanceWithoutConstructor(),
            $repo,
        );

        $result = $tool->proposeCreditNote(reasonCode: '99', taxDocumentId: 'td-1');
        self::assertArrayHasKey('error', $result);
        self::assertStringContainsString('catálogo SUNAT 09', $result['error']);
    }
}

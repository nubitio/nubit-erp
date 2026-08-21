<?php

declare(strict_types=1);

namespace App\Tests\AI\Tool;

use App\AI\Service\ProposedActionFactory;
use App\AI\Tool\ProposeReplaceRejectedTool;
use App\Billing\Entity\TaxDocument;
use App\Billing\Repository\TaxDocumentRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ProposeReplaceRejectedToolTest extends TestCase
{
    public function testRefusesNonRejectedDocuments(): void
    {
        $document = $this->createStub(TaxDocument::class);
        $document->method('getSeries')->willReturn('F001');
        $document->method('getNumber')->willReturn('1');
        $document->method('getSunatStatus')->willReturn('0');

        $repo = $this->createMock(TaxDocumentRepository::class);
        $repo->expects(self::once())->method('find')->with('td-1')->willReturn($document);

        $tool = new ProposeReplaceRejectedTool(
            new ReflectionClass(ProposedActionFactory::class)->newInstanceWithoutConstructor(),
            $repo,
        );

        $result = $tool->proposeReplaceRejected(taxDocumentId: 'td-1');
        self::assertArrayHasKey('error', $result);
        self::assertStringContainsString('no está rechazado', $result['error']);
    }

    public function testReturnsErrorWhenDocumentIsMissing(): void
    {
        $repo = $this->createMock(TaxDocumentRepository::class);
        $repo->expects(self::once())->method('find')->with('missing')->willReturn(null);

        $tool = new ProposeReplaceRejectedTool(
            new ReflectionClass(ProposedActionFactory::class)->newInstanceWithoutConstructor(),
            $repo,
        );

        $result = $tool->proposeReplaceRejected(taxDocumentId: 'missing');
        self::assertSame(['error' => 'No se encontró el comprobante.'], $result);
    }
}

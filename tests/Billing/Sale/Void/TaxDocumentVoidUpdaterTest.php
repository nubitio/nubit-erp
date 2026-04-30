<?php

declare(strict_types=1);

namespace App\Tests\Billing\Sale\Void;

use App\Billing\Entity\TaxDocument;
use App\Billing\Repository\TaxDocumentEventRepository;
use App\Billing\Sale\Void\SunatVoidResponse;
use App\Billing\Sale\Void\TaxDocumentVoidUpdater;
use App\Billing\Sunat\Model\Response\SummaryResponse;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class TaxDocumentVoidUpdaterTest extends TestCase
{
    public function testApplyVoidResultPersistsAndRecordsEvent(): void
    {
        $taxDocument = new TaxDocument();
        $summary = new SummaryResponse();
        $response = new SunatVoidResponse(
            rawResponse: $summary,
            name: '20123456789-RC-20241201-00042',
            ticket: 'TKT-9999',
            success: true,
            message: 'Comunicación de baja enviada',
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($taxDocument);

        $eventRepo = $this->createMock(TaxDocumentEventRepository::class);
        $eventRepo->expects(self::once())->method('recordEvent')->with(
            taxDocument: $taxDocument,
            eventType: 'sunat_void',
            statusCode: 'TKT-9999',
            statusMessage: 'Comunicación de baja enviada',
            payload: ['voided' => 1],
        );

        $updater = new TaxDocumentVoidUpdater($entityManager, $eventRepo);
        $updater->applyVoidResult($taxDocument, $response, 1);

        self::assertSame('00042', $taxDocument->getVoidCorrelation());
        self::assertSame('TKT-9999', $taxDocument->getVoidTicket());
    }

    public function testApplyVoidResultUsesNullMessageFallback(): void
    {
        $taxDocument = new TaxDocument();
        $summary = new SummaryResponse();
        $response = new SunatVoidResponse(
            rawResponse: $summary,
            name: '20123456789-RC-20241201-00001',
            ticket: 'TKT-0001',
            success: true,
            message: null,
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');

        $eventRepo = $this->createMock(TaxDocumentEventRepository::class);
        $eventRepo->expects(self::once())->method('recordEvent')->with(
            taxDocument: $taxDocument,
            eventType: 'sunat_void',
            statusCode: 'TKT-0001',
            statusMessage: 'Solicitud de anulación',
            payload: ['voided' => 0],
        );

        $updater = new TaxDocumentVoidUpdater($entityManager, $eventRepo);
        $updater->applyVoidResult($taxDocument, $response, 0);
    }
}
